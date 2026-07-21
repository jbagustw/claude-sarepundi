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

    /**
     * Check whether [checkIn, checkOut) is bookable for the given villa and,
     * if so, price it out. Used by both the public availability-check
     * endpoint and booking creation, so the two can never disagree.
     *
     * @return array{available: bool, reason: ?string, nights: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    public function evaluate(Villa $villa, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guestCount): array
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

        $totalPrice = 0;
        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $override = $overrides->get($date->toDateString());

            if ($override && ! $override->is_available) {
                return $this->unavailable("Tanggal {$date->toDateString()} tidak tersedia.");
            }

            $totalPrice += $override?->custom_price ?? $villa->base_price;
        }

        $hasConflict = Booking::where('villa_id', $villa->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Tanggal yang dipilih sudah dipesan.');
        }

        $commissionAmount = (int) round($totalPrice * 0.10);

        return [
            'available' => true,
            'reason' => null,
            'nights' => $nights,
            'total_price' => $totalPrice,
            'commission_amount' => $commissionAmount,
            'mitra_payout_amount' => $totalPrice - $commissionAmount,
        ];
    }

    /**
     * @return array{available: bool, reason: ?string, nights: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'nights' => 0,
            'total_price' => 0,
            'commission_amount' => 0,
            'mitra_payout_amount' => 0,
        ];
    }
}
