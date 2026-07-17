<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class BulkMoveTimetableEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moves' => ['required', 'array', 'min:1'],
            'moves.*.entry_id' => ['required', 'integer', 'distinct', 'exists:timetable_entries,id'],
            'moves.*.weekday' => ['required', 'integer', 'between:1,7'],
            'moves.*.period_number' => ['required', 'integer', 'min:1'],
            'moves.*.start_time' => ['required', 'date_format:H:i'],
            'moves.*.end_time' => ['required', 'date_format:H:i'],
            'moves.*.is_locked' => ['sometimes', 'boolean'],
        ];
    }
}
