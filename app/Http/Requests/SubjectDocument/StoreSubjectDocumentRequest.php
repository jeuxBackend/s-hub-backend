<?php

namespace App\Http\Requests\SubjectDocument;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSubjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'document_type' => ['required', 'in:yearly_syllabus,study_material'],
            'academic_year' => ['nullable', 'string', 'max:100'],
            'term' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv', 'max:5120'],
            'materials' => ['nullable', 'array', 'min:1'],
            'materials.*.title' => ['nullable', 'string', 'max:255'],
            'materials.*.description' => ['nullable', 'string'],
            'materials.*.file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $documentType = $this->input('document_type');

            if ($documentType === 'yearly_syllabus') {
                if (!$this->filled('title')) {
                    $validator->errors()->add('title', 'The title field is required for yearly syllabus.');
                }

                if (!$this->hasFile('file')) {
                    $validator->errors()->add('file', 'The file field is required for yearly syllabus.');
                }
            }

            if ($documentType === 'study_material') {
                $materials = $this->input('materials', []);

                if (!is_array($materials) || count($materials) < 1) {
                    $validator->errors()->add('materials', 'At least one study material item is required.');

                    return;
                }

                foreach (array_keys($materials) as $index) {
                    if (!filled($this->input("materials.{$index}.title"))) {
                        $validator->errors()->add("materials.{$index}.title", 'The title field is required.');
                    }

                    if (!$this->hasFile("materials.{$index}.file")) {
                        $validator->errors()->add("materials.{$index}.file", 'The file field is required.');
                    }
                }
            }
        });
    }
}
