<?php

namespace App\Http\Requests\Classname;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'exists:institutions,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classnames', 'name')->where('institution_id', $this->input('institution_id')),
            ],
        ];
    }
}
