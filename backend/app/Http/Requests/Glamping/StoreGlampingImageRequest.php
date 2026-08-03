<?php

namespace App\Http\Requests\Glamping;

use Illuminate\Foundation\Http\FormRequest;

class StoreGlampingImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('glamping'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:4096'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
