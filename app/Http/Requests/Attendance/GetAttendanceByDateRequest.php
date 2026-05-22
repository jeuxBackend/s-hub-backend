<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class GetAttendanceByDateRequest extends FormRequest
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
            //
             'date'         => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'student_id'   => ['nullable', 'exists:students,id'],
            'subject_id'   => ['nullable', 'exists:subjects,id'],
            'paginate'     => ['nullable', 'boolean'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
