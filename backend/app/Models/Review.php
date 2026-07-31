<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'booking_id',
    'user_id',
    'reviewable_type',
    'reviewable_id',
    'rating',
    'comment',
    'mitra_reply',
    'mitra_replied_at',
])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'mitra_replied_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The listing being reviewed — a Villa or a Homestay today. Mirrors
     * Booking::bookable() (see Booking::bookableTypes()).
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Every review across every listing type owned by this mitra —
     * mirrors Booking::scopeForMitra().
     */
    public function scopeForMitra(Builder $query, MitraProfile $mitra): Builder
    {
        $hasAnyCondition = false;

        return $query->where(function (Builder $outer) use ($mitra, &$hasAnyCondition) {
            foreach (Booking::bookableTypes() as $type) {
                $ids = $type::where('mitra_id', $mitra->id)->pluck('id');

                if ($ids->isEmpty()) {
                    continue;
                }

                $hasAnyCondition = true;
                $outer->orWhere(fn (Builder $inner) => $inner
                    ->where('reviewable_type', $type)
                    ->whereIn('reviewable_id', $ids));
            }

            if (! $hasAnyCondition) {
                $outer->whereRaw('1 = 0');
            }
        });
    }
}
