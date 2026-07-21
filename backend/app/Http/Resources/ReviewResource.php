<?php

namespace App\Http\Resources;

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
            'villa_id' => $this->villa_id,
            'villa' => [
                'id' => $this->villa->id,
                'name' => $this->villa->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
