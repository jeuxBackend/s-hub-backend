<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class FilterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'term'         => ['nullable', 'string'], // or TermType enum
            'gender'       => ['nullable', 'string'], // GenderType enum
            'age'          => ['nullable', 'integer'],
            'search'       => ['nullable', 'string'],
        ];
    }
}
