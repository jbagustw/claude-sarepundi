<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a user-entered coupon code into a discount amount, shared by
 * every category's AvailabilityService so the same validation (active,
 * not expired, code exists) and discount math (percentage/fixed, capped
 * at the subtotal so total_price can never go negative) runs everywhere.
 */
class CouponService
{
    /**
     * @return array{coupon_id: ?int, discount_amount: int}
     */
    public function resolve(?string $code, int $subtotal): array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return ['coupon_id' => null, 'discount_amount' => 0];
        }

        $coupon = Coupon::visible()
            ->whereRaw('UPPER(code) = ?', [Str::upper($code)])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Kode kupon tidak ditemukan atau sudah tidak berlaku.'],
            ]);
        }

        $discount = $coupon->discount_type === 'percentage'
            ? (int) round($subtotal * $coupon->discount_value / 100)
            : $coupon->discount_value;

        return [
            'coupon_id' => $coupon->id,
            'discount_amount' => min($discount, $subtotal),
        ];
    }
}
