<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Glamping;
use Carbon\CarbonImmutable;

/**
 * Homestay-equivalent for Glamping bookings — flat base_price x nights,
 * the only thing that can make a range unavailable is an overlapping
 * booking. Same return shape as the other AvailabilityService classes so
 * BookingController can treat all categories uniformly.
 */
class GlampingAvailabilityService
{
    private const BLOCKING_STATUSES = [
        'pending_payment',
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
    public function evaluate(Glamping $glamping, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guestCount, ?string $couponCode = null): array
    {
        $nights = $checkIn->diffInDays($checkOut);

        if ($guestCount > $glamping->capacity_guest) {
            return $this->unavailable("Kapasitas glamping maksimal {$glamping->capacity_guest} tamu.");
        }

        $hasConflict = Booking::where('bookable_type', Glamping::class)
            ->where('bookable_id', $glamping->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Tanggal yang dipilih sudah dipesan.');
        }

        $subtotal = $nights * $glamping->base_price;

        ['coupon_id' => $couponId, 'discount_amount' => $discountAmount] = $this->couponService->resolve($couponCode, $subtotal);
        $totalPrice = $subtotal - $discountAmount;

        $commissionRate = $glamping->mitraProfile->effectiveCommissionRate();
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
