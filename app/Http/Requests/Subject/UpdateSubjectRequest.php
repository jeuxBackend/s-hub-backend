<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:100'],
            'code'         => ['nullable', 'string', 'max:50'],
            'classroom_id' => ['sometimes', 'exists:classrooms,id'],
            'teacher_id'   => ['nullable', 'exists:users,id'],
            'lectures_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
        ];
    }
}
