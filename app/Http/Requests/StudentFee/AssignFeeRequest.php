<?php

namespace App\Http\Requests\StudentFee;

use Illuminate\Foundation\Http\FormRequest;

class AssignFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adjust if you need role-based authorization
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'term' => ['required', 'exists:classrooms,id'],
            'tuition_fee' => ['nullable', 'numeric'],
            'uniform_fee' => ['nullable', 'numeric'],
            'meals_fee' => ['nullable', 'numeric'],
            'books_fee' => ['nullable', 'numeric'],
            'other_fee' => ['nullable', 'numeric'],
            'paid_amount' => ['nullable', 'numeric'],
            'due_date' => ['nullable', 'date'],
            'paid_date' => ['nullable'],
            'status' => ['nullable', 'in:paid,unpaid,partial'],
        ];
    }
}
