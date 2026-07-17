<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class SwapTimetableEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_id_a' => ['required', 'integer', 'exists:timetable_entries,id', 'different:entry_id_b'],
            'entry_id_b' => ['required', 'integer', 'exists:timetable_entries,id'],
            'lock_after_swap' => ['sometimes', 'boolean'],
        ];
    }
}
