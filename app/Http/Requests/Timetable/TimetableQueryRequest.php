<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class TimetableQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_id' => ['nullable', 'integer', 'exists:school_timetable_configs,id'],
            'academic_year' => ['nullable', 'string', 'max:100'],
            'term' => ['nullable', 'string', 'max:100'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'period_number' => ['nullable', 'integer', 'min:1'],
            'entry_type' => ['nullable', 'string', 'in:lesson,break,lunch,custom'],
            'is_locked' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'string', 'in:daily,weekly,monthly,yearly'],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'grouped' => ['nullable', 'boolean'],
            'export' => ['nullable', 'string', 'in:csv'],
        ];
    }
}
