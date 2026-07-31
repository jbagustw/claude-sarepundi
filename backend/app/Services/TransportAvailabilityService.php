<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Transport;
use Carbon\CarbonImmutable;

/**
 * Villa/Homestay-equivalent for Transport rentals: date-range based like
 * Homestay (flat price x days, no per-date calendar), but the per-day
 * price depends on whether the renter wants a driver — a listing may only
 * offer one of the two options, so that's validated here too.
 */
class TransportAvailabilityService
{
    private const BLOCKING_STATUSES = [
        'pending_payment',
        'menunggu_konfirmasi',
        'dikonfirmasi',
        'checked_in',
        'selesai',
    ];

    /**
     * @return array{available: bool, reason: ?string, nights: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    public function evaluate(Transport $transport, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guestCount, bool $withDriver): array
    {
        $days = $checkIn->diffInDays($checkOut);

        if ($guestCount > $transport->capacity) {
            return $this->unavailable("Kapasitas kendaraan maksimal {$transport->capacity} orang.");
        }

        $pricePerDay = $withDriver ? $transport->price_per_day_with_driver : $transport->price_per_day_self_drive;

        if ($pricePerDay === null) {
            return $this->unavailable(
                $withDriver
                    ? 'Kendaraan ini tidak tersedia dengan sopir.'
                    : 'Kendaraan ini hanya tersedia dengan sopir.'
            );
        }

        $hasConflict = Booking::where('bookable_type', Transport::class)
            ->where('bookable_id', $transport->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Tanggal yang dipilih sudah dipesan.');
        }

        $totalPrice = $days * $pricePerDay;

        $commissionRate = $transport->mitraProfile->effectiveCommissionRate();
        $commissionAmount = (int) round($totalPrice * $commissionRate / 100);

        return [
            'available' => true,
            'reason' => null,
            'nights' => $days,
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
