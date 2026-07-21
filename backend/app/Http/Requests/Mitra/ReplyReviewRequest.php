<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class ReplyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('mitra')
            && $this->route('review')->villa->mitraProfile->user_id === $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mitra_reply' => ['required', 'string', 'max:2000'],
        ];
    }
}
