<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstituteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow if user is principal, admin, or school_admin
        return true;
        // return in_array($this->user()?->role->value, ['admin', 'principal', 'school_admin','teacher']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'academic_year' => 'nullable|string|max:255',
            'examination_system' => 'nullable|string|max:255',
            'physical_address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'alternate_email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:20',
            'subjects' => 'nullable|array',
            'subjects.*' => 'string|max:100',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'email_verified' => 'nullable|boolean',
            'phone_verified' => 'nullable|boolean',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }
}
