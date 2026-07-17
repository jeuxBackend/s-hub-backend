<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_id' => ['required', 'integer', 'exists:school_timetable_configs,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'period_number' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'entry_type' => ['sometimes', 'string', 'in:lesson,break,lunch,custom'],
            'is_locked' => ['sometimes', 'boolean'],
        ];
    }
}
