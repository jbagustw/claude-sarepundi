<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Homestay;
use Carbon\CarbonImmutable;

/**
 * Villa-equivalent for Homestay bookings. Deliberately simpler than
 * VillaAvailabilityService: homestays don't have a per-date availability
 * calendar (custom pricing / blocked dates) yet, so pricing is a flat
 * base_price x nights and the only thing that can make a range unavailable
 * is an overlapping booking. Same return shape as VillaAvailabilityService
 * so BookingController can treat both uniformly.
 */
class HomestayAvailabilityService
{
    private const BLOCKING_STATUSES = [
        'pending_payment',
        'menunggu_konfirmasi',
        'dikonfirmasi',
        'checked_in',
        'selesai',
    ];

    public function __construct(private CouponService $couponService)
    {
    }

    /**
     * @return array{available: bool, reason: ?string, nights: int, subtotal: int, coupon_id: ?int, discount_amount: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    public function evaluate(Homestay $homestay, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guestCount, ?string $couponCode = null): array
    {
        $nights = $checkIn->diffInDays($checkOut);

        if ($guestCount > $homestay->capacity_guest) {
            return $this->unavailable("Kapasitas homestay maksimal {$homestay->capacity_guest} tamu.");
        }

        $hasConflict = Booking::where('bookable_type', Homestay::class)
            ->where('bookable_id', $homestay->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Tanggal yang dipilih sudah dipesan.');
        }

        $subtotal = $nights * $homestay->base_price;

        ['coupon_id' => $couponId, 'discount_amount' => $discountAmount] = $this->couponService->resolve($couponCode, $subtotal);
        $totalPrice = $subtotal - $discountAmount;

        $commissionRate = $homestay->mitraProfile->effectiveCommissionRate();
        $commissionAmount = (int) round($totalPrice * $commissionRate / 100);

        return [
            'available' => true,
            'reason' => null,
            'nights' => $nights,
            'subtotal' => $subtotal,
            'coupon_id' => $couponId,
            'discount_amount' => $discountAmount,
            'total_price' => $totalPrice,
            'commission_amount' => $commissionAmount,
            'mitra_payout_amount' => $totalPrice - $commissionAmount,
        ];
    }

    /**
     * @return array{available: bool, reason: ?string, nights: int, subtotal: int, coupon_id: ?int, discount_amount: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'nights' => 0,
            'subtotal' => 0,
            'coupon_id' => null,
            'discount_amount' => 0,
            'total_price' => 0,
            'commission_amount' => 0,
            'mitra_payout_amount' => 0,
        ];
    }
}
