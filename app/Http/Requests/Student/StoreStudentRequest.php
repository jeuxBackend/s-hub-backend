<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\GenderType;
use App\Enums\TermType;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add role-based auth if needed
    }

    public function rules(): array
    {
        return [
            'first_name'                  => ['required', 'string'],
            'sur_name'                    => ['required', 'string'],
            'profile_picture'             => ['nullable', 'image', 'max:2048'],
            'student_phone_number'        => ['nullable', 'string'],
            'gender'                      => ['required', 'in:' . implode(',', GenderType::values())],
            'dob'                         => ['required', 'date', 'before:today'],
            'age'                         => ['nullable', 'integer', 'min:3'],
            'religion'                    => ['nullable', 'string'],
            'term'                        => ['required', 'in:' . implode(',', TermType::values())],
            'classroom_id'                => ['required', 'exists:classrooms,id'],
            'guardian_id'                 => ['required', 'exists:users,id'],
            'address'                     => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('first_name')) {
            $this->merge([
                'first_name' => trim($this->input('first_name')),
                'sur_name'   => trim($this->input('sur_name')),
            ]);
        }
    }
}
