<?php

namespace App\Http\Requests\Rota;

use Illuminate\Foundation\Http\FormRequest;

class ManageRotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
            'school_start_time' => ['required', 'date_format:H:i'],
            'school_end_time' => ['required', 'date_format:H:i', 'after:school_start_time'],
            'lecture_duration_minutes' => ['required', 'integer', 'min:1'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'distinct', 'between:1,7'],
        ];
    }
}
