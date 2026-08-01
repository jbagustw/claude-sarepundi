<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Villa;
use Carbon\CarbonImmutable;

class VillaAvailabilityService
{
    /**
     * Statuses that still hold a date range (a cancelled booking frees up
     * the dates again).
     */
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
     * Check whether [checkIn, checkOut) is bookable for the given villa and,
     * if so, price it out. Used by both the public availability-check
     * endpoint and booking creation, so the two can never disagree.
     *
     * @return array{available: bool, reason: ?string, nights: int, subtotal: int, coupon_id: ?int, discount_amount: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    public function evaluate(Villa $villa, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guestCount, ?string $couponCode = null): array
    {
        $nights = $checkIn->diffInDays($checkOut);

        if ($guestCount > $villa->capacity_guest) {
            return $this->unavailable("Kapasitas villa maksimal {$villa->capacity_guest} tamu.");
        }

        $overrides = $villa->availability()
            ->whereDate('date', '>=', $checkIn->toDateString())
            ->whereDate('date', '<', $checkOut->toDateString())
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $checkInOverride = $overrides->get($checkIn->toDateString());
        if ($checkInOverride?->min_stay && $nights < $checkInOverride->min_stay) {
            return $this->unavailable("Minimum menginap {$checkInOverride->min_stay} malam untuk tanggal check-in ini.");
        }

        $subtotal = 0;
        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $override = $overrides->get($date->toDateString());

            if ($override && ! $override->is_available) {
                return $this->unavailable("Tanggal {$date->toDateString()} tidak tersedia.");
            }

            $subtotal += $override?->custom_price ?? $villa->base_price;
        }

        $hasConflict = Booking::where('bookable_type', Villa::class)
            ->where('bookable_id', $villa->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Tanggal yang dipilih sudah dipesan.');
        }

        ['coupon_id' => $couponId, 'discount_amount' => $discountAmount] = $this->couponService->resolve($couponCode, $subtotal);
        $totalPrice = $subtotal - $discountAmount;

        // Platform commission defaults to 10% (CLAUDE.md) but an admin may
        // override it per mitra from the dashboard. Computed on the
        // post-discount total — the platform absorbs the coupon's cost via
        // its own commission share, mitra earnings are untouched by promos.
        $commissionRate = $villa->mitraProfile->effectiveCommissionRate();
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
