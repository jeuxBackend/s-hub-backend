<?php

namespace App\Http\Requests\ClassroomTeacher;

use Illuminate\Foundation\Http\FormRequest;

class UnallocateTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
            return [
            'teacher_id'   => 'required|exists:users,id',
            'classroom_id' => 'required|exists:classrooms,id',
        ];
    }
}
