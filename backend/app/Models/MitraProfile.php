<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'business_name',
    'business_address',
    'legal_document_url',
    'bank_account',
    'bank_name',
    'status',
    'approved_by',
    'approved_at',
    'commission_rate',
])]
class MitraProfile extends Model
{
    /**
     * Platform-wide default commission percentage (CLAUDE.md), used
     * whenever a mitra doesn't have a commission_rate override set.
     */
    public const DEFAULT_COMMISSION_RATE = 10;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function effectiveCommissionRate(): int
    {
        return $this->commission_rate ?? self::DEFAULT_COMMISSION_RATE;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function villas(): HasMany
    {
        return $this->hasMany(Villa::class, 'mitra_id');
    }

    public function homestays(): HasMany
    {
        return $this->hasMany(Homestay::class, 'mitra_id');
    }

    public function gatheringVenues(): HasMany
    {
        return $this->hasMany(GatheringVenue::class, 'mitra_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'mitra_id');
    }
}
