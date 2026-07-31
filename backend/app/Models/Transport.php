<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'mitra_id',
    'name',
    'slug',
    'vehicle_type',
    'description',
    'capacity',
    'city',
    'province',
    'price_per_day_self_drive',
    'price_per_day_with_driver',
    'status',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class Transport extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function mitraProfile(): BelongsTo
    {
        return $this->belongsTo(MitraProfile::class, 'mitra_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TransportImage::class)->orderBy('sort_order');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Transport listings that are safe to show in public search/detail
     * pages — same visibility rule as Villa/Homestay/GatheringVenue.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereHas('mitraProfile', fn (Builder $q) => $q->where('status', 'approved')
                ->whereHas('user', fn (Builder $uq) => $uq->where('status', 'active')));
    }
}
