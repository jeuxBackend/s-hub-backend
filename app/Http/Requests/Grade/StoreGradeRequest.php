<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
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
            'student_id'   => 'required|exists:students,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'subject_id' => 'required|exists:subjects,id',
            'term' => ['nullable', 'in:first,second,third,final'],
            'score' => 'nullable',
            'remarks' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'type' => ['nullable'],
            'total' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
