<?php

namespace App\Actions\StudentFee;

use App\Models\StudentFee;

class AssignFeeAction
{
    public function handle(array $data): StudentFee
    {
        return StudentFee::create([
            'student_id'   => $data['student_id'],
            'term'         => $data['term'],
            'tuition_fee'  => $data['tuition_fee'] ?? 0,
            'uniform_fee'  => $data['uniform_fee'] ?? 0,
            'meals_fee'    => $data['meals_fee'] ?? 0,
            'books_fee'    => $data['books_fee'] ?? 0,
            'other_fee'    => $data['other_fee'] ?? 0,
            'paid_amount'  => $data['paid_amount'] ?? 0,
            'due_date'     => $data['due_date'] ?? null,
            'status'       => $data['status'] ?? 'unpaid',
        ]);
    }
}
