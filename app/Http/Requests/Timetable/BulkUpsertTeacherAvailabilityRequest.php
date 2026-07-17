<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpsertTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_availabilities' => ['required', 'array', 'min:1'],
            'teacher_availabilities.*.teacher_id' => ['required', 'integer', 'exists:users,id'],
            'teacher_availabilities.*.weekday' => ['required', 'integer', 'between:1,7'],
            'teacher_availabilities.*.start_time' => ['required', 'date_format:H:i'],
            'teacher_availabilities.*.end_time' => ['required', 'date_format:H:i'],
            'teacher_availabilities.*.availability_type' => ['required', 'string', 'in:available,unavailable,preferred'],
        ];
    }
}
