<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSchoolTimetableConfigRequest extends FormRequest
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
            'mode' => ['required', 'string', 'in:primary,secondary,college,university,custom'],
            'school_start_time' => ['required', 'date_format:H:i'],
            'school_end_time' => ['required', 'date_format:H:i', 'after:school_start_time'],
            'lesson_duration_minutes' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'working_days.*.is_open' => ['sometimes', 'boolean'],
            'break_periods' => ['nullable', 'array'],
            'break_periods.*.weekday' => ['nullable', 'integer', 'between:1,7'],
            'break_periods.*.name' => ['required_with:break_periods', 'string', 'max:100'],
            'break_periods.*.break_type' => ['sometimes', 'string', 'in:break,lunch,custom'],
            'break_periods.*.start_time' => ['required_with:break_periods', 'date_format:H:i'],
            'break_periods.*.end_time' => ['required_with:break_periods', 'date_format:H:i'],
        ];
    }
}
