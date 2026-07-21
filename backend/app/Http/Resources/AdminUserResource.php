<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
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
            'status' => $this->status,
            'role' => $this->getRoleNames()->first(),
            'mitra_profile' => $this->whenLoaded('mitraProfile', fn () => $this->mitraProfile ? [
                'id' => $this->mitraProfile->id,
                'business_name' => $this->mitraProfile->business_name,
                'status' => $this->mitraProfile->status,
                'commission_rate' => $this->mitraProfile->commission_rate,
                'effective_commission_rate' => $this->mitraProfile->effectiveCommissionRate(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
