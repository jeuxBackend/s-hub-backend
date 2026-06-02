<?php

namespace App\Http\Requests\StudentFee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'exists:students,id'],
            'term' => ['sometimes', 'exists:classrooms,id'],
            'tuition_fee' => ['sometimes', 'numeric'],
            'uniform_fee' => ['sometimes', 'numeric'],
            'meals' => ['sometimes', 'numeric'],
            'books' => ['sometimes', 'numeric'],
            'others' => ['sometimes', 'numeric'],
            'paid_amount' => ['sometimes', 'numeric'],
            'due_date' => ['sometimes', 'date'],
            'paid_date' => ['sometimes'],
            'status' => ['sometimes', 'in:paid,unpaid,partial'],
        ];
    }
}
