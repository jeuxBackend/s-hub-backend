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
            'teacher_id'   => 'required|exists:users,id',
            'classroom_id' => 'required|exists:classrooms,id',
            // 'assigned_by'  => 'required|exists:users,id',
            'term'         => 'required|string|max:50',
            'year'         => 'required|integer|min:2000|max:2100',
            'section'      => 'nullable|string|max:50',
        ];
    }
}
