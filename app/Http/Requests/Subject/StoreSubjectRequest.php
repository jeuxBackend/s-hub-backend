<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:100'],
            'code'         => ['nullable', 'string', 'max:50','unique:subjects,code'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
        ];
    }
}
