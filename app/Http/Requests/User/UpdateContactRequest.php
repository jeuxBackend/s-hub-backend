<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ignoredUserId = $this->user()?->id;

        return [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($ignoredUserId)],
            'phone_number' => ['nullable', 'string', 'max:15', Rule::unique('users')->ignore($ignoredUserId)],
        ];
    }
}
