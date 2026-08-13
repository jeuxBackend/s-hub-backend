<?php

namespace App\Http\Requests\Classname;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $classname = $this->route('classname');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('classnames', 'name')
                    ->where('institution_id', $classname?->institution_id)
                    ->ignore($classname?->id),
            ],
        ];
    }
}
