<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GatheringVenue;
use App\Models\GatheringVenueSlot;
use Carbon\CarbonImmutable;

/**
 * Gathering venues are booked per time slot per day, not per night, so
 * this doesn't follow VillaAvailabilityService/HomestayAvailabilityService's
 * check_in/check_out range shape. A slot is "taken" for a given date once
 * any non-cancelled booking exists for that exact venue+slot+date triple.
 */
class GatheringVenueAvailabilityService
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
     * Availability of every active slot for a given date — used to render
     * the slot picker on the venue's public page. No coupon here: there's
     * no single price to discount until a specific slot is picked.
     *
     * @return array{date: string, slots: array<int, array{id: int, name: string, start_time: string, end_time: string, price: int, available: bool}>}
     */
    public function evaluateSlotsForDate(GatheringVenue $venue, CarbonImmutable $date): array
    {
        $bookedSlotIds = Booking::where('bookable_type', GatheringVenue::class)
            ->where('bookable_id', $venue->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->whereDate('check_in_date', $date->toDateString())
            ->pluck('gathering_venue_slot_id');

        $slots = $venue->slots()->where('is_active', true)->get();

        return [
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (GatheringVenueSlot $slot) => [
                'id' => $slot->id,
                'name' => $slot->name,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'price' => $slot->price,
                'available' => ! $bookedSlotIds->contains($slot->id),
            ])->values()->all(),
        ];
    }

    /**
     * Re-validate + price a specific slot+date at booking-creation time
     * (the picker's list can be stale by the time the user submits).
     *
     * @return array{available: bool, reason: ?string, subtotal: int, coupon_id: ?int, discount_amount: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    public function evaluate(GatheringVenue $venue, GatheringVenueSlot $slot, CarbonImmutable $date, int $guestCount, ?string $couponCode = null): array
    {
        if ($slot->gathering_venue_id !== $venue->id || ! $slot->is_active) {
            return $this->unavailable('Slot tidak ditemukan untuk lokasi ini.');
        }

        if ($guestCount > $venue->capacity) {
            return $this->unavailable("Kapasitas lokasi maksimal {$venue->capacity} tamu.");
        }

        $hasConflict = Booking::where('bookable_type', GatheringVenue::class)
            ->where('bookable_id', $venue->id)
            ->where('gathering_venue_slot_id', $slot->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->whereDate('check_in_date', $date->toDateString())
            ->exists();

        if ($hasConflict) {
            return $this->unavailable('Slot ini sudah dipesan untuk tanggal tersebut.');
        }

        $subtotal = $slot->price;

        ['coupon_id' => $couponId, 'discount_amount' => $discountAmount] = $this->couponService->resolve($couponCode, $subtotal);
        $totalPrice = $subtotal - $discountAmount;

        $commissionRate = $venue->mitraProfile->effectiveCommissionRate();
        $commissionAmount = (int) round($totalPrice * $commissionRate / 100);

        return [
            'available' => true,
            'reason' => null,
            'subtotal' => $subtotal,
            'coupon_id' => $couponId,
            'discount_amount' => $discountAmount,
            'total_price' => $totalPrice,
            'commission_amount' => $commissionAmount,
            'mitra_payout_amount' => $totalPrice - $commissionAmount,
        ];
    }

    /**
     * @return array{available: bool, reason: ?string, subtotal: int, coupon_id: ?int, discount_amount: int, total_price: int, commission_amount: int, mitra_payout_amount: int}
     */
    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'subtotal' => 0,
            'coupon_id' => null,
            'discount_amount' => 0,
            'total_price' => 0,
            'commission_amount' => 0,
            'mitra_payout_amount' => 0,
        ];
    }
}
