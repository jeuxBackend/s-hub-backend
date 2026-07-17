<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpsertClassSubjectRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*.classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'requirements.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'requirements.*.teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'requirements.*.lessons_per_week' => ['required', 'integer', 'min:1'],
            'requirements.*.double_period_allowed' => ['sometimes', 'boolean'],
            'requirements.*.priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'requirements.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
