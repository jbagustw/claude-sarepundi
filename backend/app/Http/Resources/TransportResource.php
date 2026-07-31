<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'vehicle_type' => $this->vehicle_type,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'city' => $this->city,
            'province' => $this->province,
            'price_per_day_self_drive' => $this->price_per_day_self_drive,
            'price_per_day_with_driver' => $this->price_per_day_with_driver,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'mitra' => [
                'id' => $this->mitraProfile->id,
                'business_name' => $this->mitraProfile->business_name,
            ],
            'images' => TransportImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
