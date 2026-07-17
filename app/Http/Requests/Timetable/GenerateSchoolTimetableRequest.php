<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSchoolTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_id' => ['nullable', 'integer', 'exists:school_timetable_configs,id'],
            'notify_teachers' => ['sometimes', 'boolean'],
        ];
    }
}
