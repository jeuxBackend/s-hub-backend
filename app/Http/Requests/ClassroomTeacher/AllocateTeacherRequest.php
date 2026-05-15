<?php

namespace App\Http\Requests\ClassroomTeacher;

use Illuminate\Foundation\Http\FormRequest;

class AllocateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id'   => 'required_without:teacher_ids|exists:users,id',
            'teacher_ids'  => 'required_without:teacher_id|array',
            'teacher_ids.*'=> 'exists:users,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'term'         => 'nullable|string|max:50',
            'year'         => 'nullable|integer|min:2000|max:2100',
            'section'      => 'nullable|string|max:50',
        ];
    }
}
