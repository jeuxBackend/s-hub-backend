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
            'type' => ['nullable', 'string', 'max:50'], // Allow custom types like test_1, test_2, etc.
            'total' => 'nullable|numeric|min:0',
            'file' => 'nullable|file|mimes:pdf,jpg,png,jpeg,doc,docx|max:10240', // max 10MB
        ];
    }
}