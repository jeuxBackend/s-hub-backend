<?php

namespace App\Http\Requests\Student;

use Carbon\Carbon;
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
            'last_name'                   => ['nullable', 'string'],
            'profile_picture'             => ['nullable', 'image', 'max:2048'],
            'student_phone_number'        => ['sometimes', 'nullable', 'string'],
            'gender'                      => ['required', 'in:' . implode(',', GenderType::values())],
            'dob'                         => ['required', 'date', 'before:today'],
            'age'                         => ['nullable', 'integer', 'min:3'],
            'religion'                    => ['nullable', 'string'],
            'nationality'                 => ['nullable', 'string', 'max:100'],
            'country_of_birth'            => ['nullable', 'string', 'max:100'],
            'primary_language'            => ['nullable', 'string', 'max:100'],
            'term'                        => ['required', 'in:' . implode(',', TermType::values())],
            'classroom_id'                => ['required', 'exists:classrooms,id'],
            'guardian_id'                 => ['required', 'exists:users,id'],
            'address'                     => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('first_name')) {
            $payload['first_name'] = trim($this->input('first_name'));
            $payload['sur_name'] = trim($this->input('sur_name'));
        }

        if ($this->has('last_name')) {
            $payload['last_name'] = trim($this->input('last_name'));
        }

        if ($this->filled('dob')) {
            try {
                $payload['age'] = Carbon::parse($this->input('dob'))->age;
            } catch (\Throwable $e) {
                // Let validation handle invalid dates.
            }
        }

        if (!empty($payload)) {
            $this->merge($payload);
        }
    }
}
