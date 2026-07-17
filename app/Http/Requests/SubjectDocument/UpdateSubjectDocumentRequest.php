<?php

namespace App\Http\Requests\SubjectDocument;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['sometimes', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv', 'max:5120'],
            'academic_year' => ['nullable', 'string', 'max:100'],
            'term' => ['nullable', 'string', 'max:100'],
        ];
    }
}
