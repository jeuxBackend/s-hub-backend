<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Subject;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255', // Assignment title
            'assignment_text' => 'nullable|string', // assignment_text (nullable)
            'class_id' => 'required|exists:classrooms,id', // clss_id (changed from classroom_id)
            'subject_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $subjectExists = Subject::where('id', $value)->exists();
                    if (!$subjectExists) {
                        $fail('The selected subject is invalid.');
                    }
                },
            ],
            'status' => 'required|in:draft,assigned',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,txt|max:10240', // Max 10MB
            'submission_end_date' => 'required|date', // submission_end_date
            'assignment_date' => 'required|date', // assignment_date
        ];
    }

    public function attributes()
    {
        return [
            'class_id' => 'class',
            'subject_id' => 'subject',
        ];
    }
}