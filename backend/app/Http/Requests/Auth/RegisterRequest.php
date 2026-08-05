<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['user', 'mitra'])],
            'business_name' => ['nullable', 'required_if:role,mitra', 'string', 'max:255'],
            'business_address' => ['nullable', 'string'],
            // Sent by non-browser clients (mobile app) that want a Sanctum
            // personal access token back instead of relying on the SPA's
            // cookie session — see AuthController::register(). Web ignores
            // the token in the response, so this is safe to omit for web.
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
