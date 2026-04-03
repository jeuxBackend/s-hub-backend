<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class FilterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or use policies if needed
    }

    public function rules(): array
    {
        return [
            'role'    => 'sometimes|string|in:admin,sub_admin,principal,teacher,parent,school_admin',
            'search'  => 'sometimes|string|max:255',
            'status'  => 'sometimes|boolean',
            'limit'   => 'sometimes|integer|min:1|max:100',
            'page'    => 'sometimes|integer|min:1',
        ];
    }
}
