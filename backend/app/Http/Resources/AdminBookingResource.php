<?php

namespace App\Http\Resources;

use App\Models\GatheringVenue;
use App\Models\Glamping;
use App\Models\Homestay;
use App\Models\Transport;
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
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'total_price' => $this->total_price,
            'commission_amount' => $this->commission_amount,
            'mitra_payout_amount' => $this->mitra_payout_amount,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'refund_amount' => $this->refund_amount,
            'bookable' => [
                'type' => $this->bookableType(),
                'id' => $this->bookable->id,
                'name' => $this->bookable->name,
            ],
            'slot' => $this->whenLoaded('slot', fn () => $this->slot ? [
                'name' => $this->slot->name,
                'start_time' => substr((string) $this->slot->start_time, 0, 5),
                'end_time' => substr((string) $this->slot->end_time, 0, 5),
            ] : null),
            'transport_with_driver' => $this->transport_with_driver,
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

    private function bookableType(): string
    {
        return match (true) {
            $this->bookable instanceof Glamping => 'glamping',
            $this->bookable instanceof Homestay => 'homestay',
            $this->bookable instanceof GatheringVenue => 'gathering_venue',
            $this->bookable instanceof Transport => 'transport',
            default => 'villa',
        };
    }
}
