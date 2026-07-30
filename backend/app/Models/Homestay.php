<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mitra_id',
    'name',
    'slug',
    'description',
    'address',
    'city',
    'province',
    'latitude',
    'longitude',
    'capacity_guest',
    'bedroom_count',
    'bathroom_count',
    'base_price',
    'status',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class Homestay extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'reviewed_at' => 'datetime',
        ];
    }

    public function mitraProfile(): BelongsTo
    {
        return $this->belongsTo(MitraProfile::class, 'mitra_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HomestayImage::class)->orderBy('sort_order');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'homestay_facilities');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Homestays that are safe to show in public search/detail pages — same
     * visibility rule as Villa (see Villa::scopePubliclyVisible).
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereHas('mitraProfile', fn (Builder $q) => $q->where('status', 'approved')
                ->whereHas('user', fn (Builder $uq) => $uq->where('status', 'active')));
    }
}
