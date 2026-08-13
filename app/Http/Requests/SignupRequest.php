<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $base = [
            'email'            => ['required', 'email', 'unique:users,email'],
            'phone_number'     => ['required', 'unique:users,phone_number'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
            'role'             => ['required', Rule::enum(UserRole::class)],
            // Optional security fields
            'profile_picture'  => ['nullable', 'image'],
            'alternative_email'        => ['nullable', 'email'],
            'alternative_phone_number' => ['nullable', 'string'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'answer_security_question' => ['nullable', 'string', 'max:255'],
        ];

        $role = $this->input('role');
        $isSelfSignup = !auth()->check();

        $roleSpecific = match ($role) {
            UserRole::Principal->value => [
                'title'        => ['required', 'string'],
                'first_name'   => ['required', 'string'],
                'sur_name'     => ['required', 'string'],
                'last_name'    => ['nullable', 'string'],
                // 'position'     => ['required', 'string'],
                'staff_number' => ['nullable', 'string', 'unique:users,staff_number'],
            ],

            UserRole::Teacher->value => [
                'first_name'   => ['required', 'string'],
                'sur_name'     => ['required', 'string'],
                'last_name'    => ['nullable', 'string'],
                // 'position'     => ['required', 'string'],
                'staff_number' => ['required', 'string', 'unique:users,staff_number'],
            ],

            UserRole::Parent->value => [
                'first_name'                        => ['required', 'string'],
                'sur_name'                          => ['required', 'string'],
                'last_name'                         => ['nullable', 'string'],
                'guardian_type'                     => ['required', 'in:father,mother,guardian'],
                'guardian_name'                     => ['required', 'string'],
                'guardian_relation'                 => ['nullable', 'string'],
                'guardian_phone_number'             => ['required', 'string'],
                'alternative_guardian_phone_number' => ['nullable', 'string'],
                'nationality'                        => ['nullable', 'string', 'max:100'],
                'country_of_birth'                   => ['nullable', 'string', 'max:100'],
                'primary_language'                   => ['nullable', 'string', 'max:100'],
            ],

            UserRole::SchoolAdmin->value => [
                'first_name'   => ['required', 'string'],
                'sur_name'     => ['required', 'string'],
                'last_name'    => ['nullable', 'string'],
                // 'position'     => ['nullable', 'string'],
                'staff_number' => ['nullable', 'string', 'unique:users,staff_number'],
                'permissions'  => ['nullable', 'array'],
            ],

            default => [],
        };

        $institutionFields = [];

        if ($role === UserRole::Principal->value && $isSelfSignup) {
            $institutionFields = [
                'institution_name'             => ['required', 'string', 'max:255'],
                'institution_email'            => ['required', 'email', 'unique:institutions,email'],
                'institution_phone_number'     => ['required', 'string', 'unique:institutions,phone_number'],
                'category_id'                  => ['nullable', 'exists:categories,id'],
                'slogan'                       => ['nullable', 'string', 'max:255'],
                'logo'                         => ['nullable', 'image'],
                'subjects'                     => ['nullable', 'array'],
                'academic_year'                => ['nullable', 'string', 'max:50'],
                'examination_system'           => ['nullable', 'string', 'max:100'],
                'physical_address'             => ['nullable', 'string', 'max:255'],
                'institution_alternate_email'  => ['nullable', 'email'],
                'institution_alternate_phone'  => ['nullable', 'string'],
                'institution_telephone'        => ['nullable', 'string'],
            ];
        }

        return array_merge($base, $roleSpecific, $institutionFields);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('role')) {
            $this->merge([
                'role' => strtolower($this->input('role')),
            ]);
        }
    }
}
