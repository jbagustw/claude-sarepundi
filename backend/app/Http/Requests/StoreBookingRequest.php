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
        $table = match ($this->input('bookable_type')) {
            'homestay' => 'homestays',
            'gathering_venue' => 'gathering_venues',
            default => 'villas',
        };

        $rules = [
            'bookable_type' => ['required', 'string', Rule::in(['villa', 'homestay', 'gathering_venue'])],
            'bookable_id' => ['required', 'integer', Rule::exists($table, 'id')],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ];

        if ($this->input('bookable_type') === 'gathering_venue') {
            $rules['gathering_venue_slot_id'] = ['required', 'integer', Rule::exists('gathering_venue_slots', 'id')];
        } else {
            $rules['check_out_date'] = ['required', 'date', 'after:check_in_date'];
        }

        return $rules;
    }
}
