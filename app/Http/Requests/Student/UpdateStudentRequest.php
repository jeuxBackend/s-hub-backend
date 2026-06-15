<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // You can add role-based auth if needed
    }

    public function rules(): array
    {
        return [
            'first_name'                 => ['sometimes', 'string'],
            'sur_name'                   => ['sometimes', 'string'],
            'profile_picture'            => ['nullable', 'image'],
            'student_phone_number'       => ['nullable', 'string'],
            'term'                       => ['nullable', 'string'], // You may use enum validation here
            'classroom_id'               => ['nullable', 'exists:classrooms,id'],
            // 'gender'                     => ['nullable', 'string'], // Enum: GenderType
            'dob'                        => ['nullable', 'date', 'before:today'],
            'age'                        => ['nullable', 'integer'],
            'religion'                   => ['nullable', 'string'],
            'address'                    => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim($this->input('first_name')),
            'sur_name'   => trim($this->input('sur_name')),
        ]);
    }
}
