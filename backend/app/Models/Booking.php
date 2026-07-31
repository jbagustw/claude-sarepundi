<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'booking_code',
    'user_id',
    'bookable_type',
    'bookable_id',
    'gathering_venue_slot_id',
    'check_in_date',
    'check_out_date',
    'guest_count',
    'total_price',
    'commission_amount',
    'mitra_payout_amount',
    'status',
    'mitra_confirmed_at',
    'mitra_confirmation_deadline',
    'cancellation_reason',
    'cancelled_at',
    'refund_amount',
    'refund_percentage',
    'payout_id',
])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'mitra_confirmed_at' => 'datetime',
            'mitra_confirmation_deadline' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The listing being booked — Villa, Homestay, or GatheringVenue today,
     * more categories (transport) as they become bookable.
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only set when bookable_type is GatheringVenue — the specific time
     * slot that was booked for that day.
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(GatheringVenueSlot::class, 'gathering_venue_slot_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function nights(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    /**
     * Model classes that are currently bookable and carry a `mitra_id`
     * pointing at mitra_profiles — used by scopeForMitra to find every
     * booking across a mitra's listings regardless of category. Extend
     * this list as gathering venues / transport become bookable too.
     *
     * @return array<class-string>
     */
    public static function bookableTypes(): array
    {
        return [Villa::class, Homestay::class, GatheringVenue::class];
    }

    /**
     * Every booking across every listing type owned by this mitra —
     * the polymorphic equivalent of the old `whereHas('villa', ...)`.
     */
    public function scopeForMitra(Builder $query, MitraProfile $mitra): Builder
    {
        $hasAnyCondition = false;

        return $query->where(function (Builder $outer) use ($mitra, &$hasAnyCondition) {
            foreach (self::bookableTypes() as $type) {
                $ids = $type::where('mitra_id', $mitra->id)->pluck('id');

                if ($ids->isEmpty()) {
                    continue;
                }

                $hasAnyCondition = true;
                $outer->orWhere(fn (Builder $inner) => $inner
                    ->where('bookable_type', $type)
                    ->whereIn('bookable_id', $ids));
            }

            if (! $hasAnyCondition) {
                $outer->whereRaw('1 = 0');
            }
        });
    }
}
