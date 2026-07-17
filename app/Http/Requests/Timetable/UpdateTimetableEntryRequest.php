<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'classroom_id' => ['sometimes', 'integer', 'exists:classrooms,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'weekday' => ['sometimes', 'integer', 'between:1,7'],
            'period_number' => ['sometimes', 'integer', 'min:1'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'is_locked' => ['sometimes', 'boolean'],
        ];
    }
}
