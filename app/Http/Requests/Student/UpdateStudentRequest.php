<?php

namespace App\Http\Requests\Student;

use Carbon\Carbon;
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
            'student_phone_number'       => ['sometimes', 'nullable', 'string'],
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
        $payload = [
            'first_name' => trim($this->input('first_name')),
            'sur_name'   => trim($this->input('sur_name')),
        ];

        if ($this->filled('dob')) {
            try {
                $payload['age'] = Carbon::parse($this->input('dob'))->age;
            } catch (\Throwable $e) {
                // Let validation handle invalid dates.
            }
        }

        $this->merge($payload);
    }
}
