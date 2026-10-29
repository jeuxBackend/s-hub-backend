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
            'first_name'                 => ['sometimes', 'string', 'max:255'],
            'sur_name'                   => ['sometimes', 'string', 'max:255'],
            'last_name'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_picture'            => ['nullable', 'image', 'max:2048'],
            'student_phone_number'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'term'                       => ['sometimes', 'nullable', 'string'],
            'classroom_id'               => ['sometimes', 'nullable', 'exists:classrooms,id'],
            'dob'                        => ['sometimes', 'nullable', 'date', 'before:today'],
            'age'                        => ['sometimes', 'nullable', 'integer'],
            'religion'                   => ['sometimes', 'nullable', 'string', 'max:255'],
            'nationality'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'country_of_birth'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'primary_language'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'                    => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('first_name')) {
            $payload['first_name'] = trim((string) $this->input('first_name'));
        }

        if ($this->has('sur_name')) {
            $payload['sur_name'] = trim((string) $this->input('sur_name'));
        }

        if ($this->has('last_name')) {
            $trimmed = trim((string) $this->input('last_name'));
            $payload['last_name'] = $trimmed !== '' ? $trimmed : null;
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
