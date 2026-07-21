<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MitraBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'check_in_date' => $this->check_in_date->toDateString(),
            'check_out_date' => $this->check_out_date->toDateString(),
            'guest_count' => $this->guest_count,
            'total_price' => $this->total_price,
            'mitra_payout_amount' => $this->mitra_payout_amount,
            'status' => $this->status,
            'mitra_confirmation_deadline' => $this->mitra_confirmation_deadline?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'villa' => [
                'id' => $this->villa->id,
                'name' => $this->villa->name,
            ],
            'guest' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
