<?php

namespace App\Http\Resources;

use App\Models\GatheringVenue;
use App\Models\Homestay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'mitra_reply' => $this->mitra_reply,
            'mitra_replied_at' => $this->mitra_replied_at?->toIso8601String(),
            'user' => [
                'name' => $this->user->name,
            ],
            'reviewable' => [
                'type' => match (true) {
                    $this->reviewable instanceof Homestay => 'homestay',
                    $this->reviewable instanceof GatheringVenue => 'gathering_venue',
                    default => 'villa',
                },
                'id' => $this->reviewable->id,
                'name' => $this->reviewable->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
