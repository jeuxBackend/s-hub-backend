<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:classrooms,code'],
            'in_charge_id' => ['nullable', 'exists:users,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.name' => ['required_with:subjects', 'string', 'max:100'],
            'subjects.*.teacher_id' => ['nullable', 'exists:users,id'],
            'subjects.*.lectures_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'subjects.*.start_time' => ['nullable'],
            'subjects.*.end_time' => ['nullable'],
        ];
    }
}
