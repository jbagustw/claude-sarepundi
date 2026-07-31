<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatheringVenueResource extends JsonResource
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
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviews_avg_rating' => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : null,
            'reviews_count' => $this->reviews_count ?? 0,
            'mitra' => [
                'id' => $this->mitraProfile->id,
                'business_name' => $this->mitraProfile->business_name,
            ],
            'images' => GatheringVenueImageResource::collection($this->whenLoaded('images')),
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'slots' => GatheringVenueSlotResource::collection($this->whenLoaded('slots')),
            'starting_price' => $this->whenLoaded('slots', fn () => $this->slots->where('is_active', true)->min('price')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
