<?php

namespace App\Http\Requests\Villa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('villa'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['date'],
            'is_available' => ['required', 'boolean'],
            'custom_price' => ['nullable', 'integer', 'min:0'],
            'min_stay' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
