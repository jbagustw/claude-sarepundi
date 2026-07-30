<?php

namespace App\Http\Requests\GatheringVenue;

use Illuminate\Foundation\Http\FormRequest;

class StoreGatheringVenueSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('gatheringVenue'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }
}
