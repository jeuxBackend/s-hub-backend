<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class GetAttendanceByMonthRequest extends FormRequest
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
           'student_id'    => ['nullable', 'exists:students,id'],
        'classroom_id'  => ['nullable', 'exists:classrooms,id'],
        'subject_id'    => ['nullable', 'exists:subjects,id'],
        'term'         => ['nullable', 'in:first,second,third,final'],
        'date'          => ['nullable', 'date'],         // for exact day
        'month'         => ['nullable', 'integer', 'between:1,12'], // optional
        'year'          => ['nullable', 'integer', 'min:2000'],     // optional
        'paginate'      => ['nullable', 'boolean'],
        'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
