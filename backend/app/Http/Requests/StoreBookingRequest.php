<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Booking::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = $this->input('bookable_type') === 'homestay' ? 'homestays' : 'villas';

        return [
            'bookable_type' => ['required', 'string', Rule::in(['villa', 'homestay'])],
            'bookable_id' => ['required', 'integer', Rule::exists($table, 'id')],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ];
    }
}
