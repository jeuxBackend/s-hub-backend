<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;

use App\Models\StudentFee;
use App\Models\Student;

class CreateStudentInvoiceAction
{
    public function handle(array $data)
    {
        $student = Student::findOrFail($data['student_id']);
        
        if (empty($data['paid_by'])) {
            $data['paid_by'] = $student->guardian_id;
        }

        $studentFee = StudentFee::where('student_id', $data['student_id'])->latest()->first();

        $amount = 0;
        if ($studentFee) {
            $amount = ($studentFee->tuition_fee ?? 0) +
                      ($studentFee->uniform_fee ?? 0) +
                      ($studentFee->meals_fee ?? 0) +
                      ($studentFee->books_fee ?? 0) +
                      ($studentFee->other_fee ?? 0);
        }

        $discount = $data['discount'] ?? 0;
        $totalAmount = $amount - $discount;

        $invoiceData = array_merge($data, [
            'amount' => $amount,
            'total_amount' => $totalAmount,
            'due_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        return StudentInvoice::create($invoiceData);
    }
}
