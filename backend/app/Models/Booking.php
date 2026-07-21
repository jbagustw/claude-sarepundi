<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_code',
    'user_id',
    'villa_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function nights(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }
}
