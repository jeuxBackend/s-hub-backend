<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class GetGradeRequest extends FormRequest
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
            'date'         => ['nullable', 'date'],
            'student_id'   => ['nullable', 'exists:students,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'subject_id'   => ['nullable', 'exists:subjects,id'],
            'term'         => ['nullable', 'in:first,second,third,final'],
 // adjust if using enum
            'type'         => ['nullable', 'string', 'max:50'],
            // 'paginate'     => ['nullable', 'boolean'],
        ];
    }
}
