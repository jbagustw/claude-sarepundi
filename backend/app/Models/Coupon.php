<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'title',
    'description',
    'discount_type',
    'discount_value',
    'valid_until',
    'is_active',
    'sort_order',
])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Coupons safe to show on the public homepage — active and not past
     * their validity date (coupons without an expiry are always shown).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()));
    }
}
