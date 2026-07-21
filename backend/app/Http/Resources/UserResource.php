<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'role' => $this->getRoleNames()->first(),
            'mitra_profile' => $this->whenLoaded('mitraProfile', fn () => $this->mitraProfile ? [
                'business_name' => $this->mitraProfile->business_name,
                'status' => $this->mitraProfile->status,
            ] : null),
        ];
    }
}
