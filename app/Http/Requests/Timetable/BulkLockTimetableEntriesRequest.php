<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class BulkLockTimetableEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer', 'distinct', 'exists:timetable_entries,id'],
            'is_locked' => ['required', 'boolean'],
        ];
    }
}
