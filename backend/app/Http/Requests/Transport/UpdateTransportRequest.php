<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transport'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicle_type' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'price_per_day_self_drive' => ['sometimes', 'nullable', 'required_without:price_per_day_with_driver', 'integer', 'min:0'],
            'price_per_day_with_driver' => ['sometimes', 'nullable', 'required_without:price_per_day_self_drive', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_per_day_self_drive.required_without' => 'Isi minimal salah satu harga: lepas kunci atau dengan supir.',
            'price_per_day_with_driver.required_without' => 'Isi minimal salah satu harga: lepas kunci atau dengan supir.',
        ];
    }
}
