<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'user_id',
    'villa_id',
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

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }
}
