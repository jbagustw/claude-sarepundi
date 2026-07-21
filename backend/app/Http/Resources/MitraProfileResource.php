<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MitraProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_address' => $this->business_address,
            'legal_document_url' => $this->legal_document_url,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'status' => $this->status,
            'commission_rate' => $this->commission_rate,
            'effective_commission_rate' => $this->effectiveCommissionRate(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
        ];
    }
}
