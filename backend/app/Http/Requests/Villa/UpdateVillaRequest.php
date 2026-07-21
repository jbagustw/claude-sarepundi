<?php

namespace App\Http\Requests\Villa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVillaRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity_guest' => ['sometimes', 'required', 'integer', 'min:1'],
            'bedroom_count' => ['nullable', 'integer', 'min:0'],
            'bathroom_count' => ['nullable', 'integer', 'min:0'],
            'base_price' => ['sometimes', 'required', 'integer', 'min:0'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ];
    }
}
