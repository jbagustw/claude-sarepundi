<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'xendit_disbursement_id' => $this->xendit_disbursement_id,
            'status' => $this->status,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'booking_count' => $this->whenCounted('bookings'),
            'mitra' => $this->whenLoaded('mitraProfile', fn () => [
                'id' => $this->mitraProfile->id,
                'business_name' => $this->mitraProfile->business_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
