<?php

namespace App\Http\Requests\Student;

use App\Enums\GenderType;
use Illuminate\Foundation\Http\FormRequest;

class FilterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_name'   => ['nullable', 'string'],
            'class_id'       => ['nullable', 'exists:classrooms,id'],
            'tuition_status' => ['nullable', 'in:paid,unpaid,partial'],
            'gender'         => ['nullable', 'in:' . implode(',', GenderType::values())],
            'age_group'      => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $rangePattern = '/^\d+\-\d+$/';
                    $plusPattern = '/^\d+\+$/';

                    if (!preg_match($rangePattern, $value) && !preg_match($plusPattern, $value)) {
                        $fail('The ' . $attribute . ' must be in "min-max" or "min+" format.');
                        return;
                    }

                    if (preg_match($rangePattern, $value)) {
                        [$min, $max] = array_map('intval', explode('-', $value));

                        if ($min > $max) {
                            $fail('The ' . $attribute . ' minimum age cannot be greater than maximum age.');
                        }
                    }
                },
            ],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
