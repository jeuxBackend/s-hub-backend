<?php

namespace App\Actions\StudentFee;

use App\Models\StudentFee;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class AssignFeeAction
{
    public function handle(array $data): StudentFee
    {

        Log::info("AssignFeeAction: " . json_encode($data));

        $totalAmount =
            ($data['tuition_fee'] ?? 0) +
            ($data['uniform_fee'] ?? 0) +
            ($data['meals_fee'] ?? 0) +
            ($data['books_fee'] ?? 0) +
            ($data['other_fee'] ?? 0);

        $paidAmount = $data['paid_amount'] ?? 0;

        $status = 'unpaid';

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $status = 'partial';
        }
        if (!empty($data['paid_date'])) {
            $paymentMonth = Carbon::parse($data['paid_date'])->format('F');
            // Example: May
        }

        return StudentFee::create([
            'student_id' => $data['student_id'],
            'class_id' => $data['term'],
            'tuition_fee' => $data['tuition_fee'] ?? 0,
            'uniform_fee' => $data['uniform_fee'] ?? 0,
            'meals_fee' => $data['meals_fee'] ?? 0,
            'books_fee' => $data['books_fee'] ?? 0,
            'other_fee' => $data['other_fee'] ?? 0,
            'paid_amount' => $paidAmount,
            'due_date' => $data['due_date'] ?? null,
            'paid_date' => $data['paid_date'] ?? null,
            'total_amount' => $totalAmount,
            'payment_month' => $paymentMonth,
            'status' => $status,
        ]);
    }
}
