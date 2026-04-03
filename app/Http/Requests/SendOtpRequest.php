<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\OtpType;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OtpType::class)],

            // conditional fields
            'email'        => ['required_if:type,email', 'email', 'exists:users,email'],
            'phone_number' => ['required_if:type,phone', 'string', 'exists:users,phone_number'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type')) {
            $this->merge([
                'type' => strtolower($this->input('type')),
            ]);
        }
    }
}
