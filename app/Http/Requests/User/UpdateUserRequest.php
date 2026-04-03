<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // You’re using policies for access control
    }

    public function rules(): array
    {
     return [
        'email'        => ['nullable', 'email', Rule::unique('users')->ignore($this->route('user'))],
        'phone_number' => ['nullable', 'string', Rule::unique('users')->ignore($this->route('user'))],
        'first_name'   => ['nullable', 'string', 'max:100'],
        'guardian_relation'   => ['nullable', 'string', 'max:100'],
        'alternative_guardian_phone_number'   => ['nullable', 'string', 'max:100'],
        'guardian_name'   => ['nullable', 'string', 'max:100'],
        'sur_name'     => ['nullable', 'string', 'max:100'],
        'title'        => ['nullable', 'string', 'max:100'],
        'position'     => ['nullable', 'string', 'max:100'],
        'staff_number' => ['nullable', 'string', 'max:100'],
        'device_id'    => ['nullable', 'string', 'max:255'],
        'fcm_token'    => ['nullable', 'string'],
        'alternative_email'             => ['nullable', 'email'],
        'alternative_phone_number'      => ['nullable', 'string'],
        'profile_picture'               => ['nullable', 'file', 'image', 'max:2048'],
        'permissions' => ['nullable', 'array'], // only for SchoolAdmins
        // 'permissions.*' => ['string'], // optionally validate each permission string
    ];
    }
}
