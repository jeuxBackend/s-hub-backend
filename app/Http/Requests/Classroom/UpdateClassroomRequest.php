<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:classrooms,code,' . $this->classroom->id],
            'in_charge_id' => ['nullable', 'exists:users,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.id' => ['nullable', 'exists:subjects,id'],
            'subjects.*.name' => ['required_with:subjects', 'string', 'max:100'],
            'subjects.*.teacher_id' => ['nullable', 'exists:users,id'],
            'subjects.*.start_time' => ['nullable', 'string'],
            'subjects.*.end_time' => ['nullable', 'string'],
        ];
    }
}
