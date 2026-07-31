<?php

namespace App\Http\Resources;

use App\Models\Homestay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBookingResource extends JsonResource
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
            'total_price' => $this->total_price,
            'commission_amount' => $this->commission_amount,
            'mitra_payout_amount' => $this->mitra_payout_amount,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'refund_amount' => $this->refund_amount,
            'bookable' => [
                'type' => $this->bookable instanceof Homestay ? 'homestay' : 'villa',
                'id' => $this->bookable->id,
                'name' => $this->bookable->name,
            ],
            'mitra' => [
                'business_name' => $this->bookable->mitraProfile->business_name,
            ],
            'user' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'payment_status' => $this->whenLoaded('latestPayment', fn () => $this->latestPayment?->status),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
