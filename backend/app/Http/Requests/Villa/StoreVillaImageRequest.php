<?php

namespace App\Http\Requests\Villa;

use Illuminate\Foundation\Http\FormRequest;

class StoreVillaImageRequest extends FormRequest
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
            'image' => ['required', 'image', 'max:4096'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
