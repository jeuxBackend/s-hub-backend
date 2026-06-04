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
            'student_name'   => ['nullable', 'string'],
            'class_id'       => ['nullable', 'exists:classrooms,id'],
            'tuition_status' => ['nullable', 'in:paid,unpaid,partial'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
