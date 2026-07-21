<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mitra_id',
    'amount',
    'period_start',
    'period_end',
    'xendit_disbursement_id',
    'status',
    'failure_reason',
    'processed_at',
])]
class Payout extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function mitraProfile(): BelongsTo
    {
        return $this->belongsTo(MitraProfile::class, 'mitra_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
