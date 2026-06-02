<?php

namespace App\Actions\StudentFee;

use App\Models\StudentFee;
use Carbon\Carbon;

class UpdateFeeAction
{
    public function handle(StudentFee $studentFee, array $data): StudentFee
    {
        $tuitionFee = $data['tuition_fee'] ?? $studentFee->tuition_fee;
        $uniformFee = $data['uniform_fee'] ?? $studentFee->uniform_fee;
        $mealsFee = $data['meals'] ?? $studentFee->meals_fee;
        $booksFee = $data['books'] ?? $studentFee->books_fee;
        $otherFee = $data['others'] ?? $studentFee->other_fee;
        $paidAmount = $data['paid_amount'] ?? $studentFee->paid_amount;
        $paidDate = $data['paid_date'] ?? $studentFee->paid_date;

        $totalAmount = $tuitionFee + $uniformFee + $mealsFee + $booksFee + $otherFee;

        $status = 'unpaid';
        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $status = 'partial';
        }

        $paymentMonth = null;
        if (!empty($paidDate)) {
            $paymentMonth = Carbon::parse($paidDate)->format('F');
        }

        $studentFee->update([
            'student_id' => $data['student_id'] ?? $studentFee->student_id,
            'class_id' => $data['term'] ?? $studentFee->class_id,
            'tuition_fee' => $tuitionFee,
            'uniform_fee' => $uniformFee,
            'meals_fee' => $mealsFee,
            'books_fee' => $booksFee,
            'other_fee' => $otherFee,
            'paid_amount' => $paidAmount,
            'due_date' => $data['due_date'] ?? $studentFee->due_date,
            'paid_date' => $paidDate,
            'total_amount' => $totalAmount,
            'payment_month' => $paymentMonth,
            'status' => $status,
        ]);

        return $studentFee->refresh();
    }
}
