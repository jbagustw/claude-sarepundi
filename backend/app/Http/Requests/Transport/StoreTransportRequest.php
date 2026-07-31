<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Transport::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'price_per_day_self_drive' => ['nullable', 'required_without:price_per_day_with_driver', 'integer', 'min:0'],
            'price_per_day_with_driver' => ['nullable', 'required_without:price_per_day_self_drive', 'integer', 'min:0'],
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
