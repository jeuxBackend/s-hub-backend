<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'], // Accepts email or phone
            'password' => ['required', 'string'],
            'institution_id' => ['nullable', 'string'], // required only for parents (handled in action)
            'device_id' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('login')) {
            $this->merge([
                'login' => trim($this->input('loginsystemctl restart php8.4-fpm
systemctl reload nginx')),
            ]);
        }
    }
}
