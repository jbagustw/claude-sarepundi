<?php

namespace App\Http\Requests\GatheringVenue;

use Illuminate\Foundation\Http\FormRequest;

class RejectGatheringVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
