<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookingResource extends JsonResource
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
            'commission_amount' => $this->commission_amount,
            'mitra_payout_amount' => $this->mitra_payout_amount,
            'status' => $this->status,
            'mitra_confirmation_deadline' => $this->mitra_confirmation_deadline?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refund_amount' => $this->refund_amount,
            'refund_percentage' => $this->refund_percentage,
            'payment' => $this->whenLoaded('latestPayment', fn () => $this->latestPayment ? [
                'status' => $this->latestPayment->status,
                'invoice_url' => $this->latestPayment->status === 'pending' ? $this->latestPayment->invoice_url : null,
                'paid_at' => $this->latestPayment->paid_at?->toIso8601String(),
            ] : null),
            'review' => $this->whenLoaded('review', fn () => $this->review ? new ReviewResource($this->review) : null),
            'villa' => [
                'id' => $this->villa->id,
                'name' => $this->villa->name,
                'slug' => $this->villa->slug,
                'city' => $this->villa->city,
                'primary_image' => $this->primaryImageUrl(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function primaryImageUrl(): ?string
    {
        $image = $this->villa->images->firstWhere('is_primary', true) ?? $this->villa->images->first();

        return $image ? Storage::disk('public')->url($image->image_url) : null;
    }
}
