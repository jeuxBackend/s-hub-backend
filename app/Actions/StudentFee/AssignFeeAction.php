<?php

namespace App\Actions\StudentFee;

use App\Models\StudentFee;
use App\Models\StudentInvoice;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;


class AssignFeeAction
{
    public function handle(array $data): StudentFee
    {
        Log::info("AssignFeeAction: " . json_encode($data));

        $student = Student::findOrFail($data['student_id']);
        $paidAmount = $data['paid_amount'] ?? 0;
        $paidDate = $data['paid_date'] ?? null;
        $paymentDate = $paidDate ? Carbon::parse($paidDate) : Carbon::now();

        $totalAmount =
            ($data['tuition_fee'] ?? 0) +
            ($data['uniform_fee'] ?? 0) +
            ($data['meals_fee'] ?? 0) +
            ($data['books_fee'] ?? 0) +
            ($data['other_fee'] ?? 0);

        $blueprint = StudentFee::create([
            'student_id' => $data['student_id'],
            'class_id' => $data['term'],
            'tuition_fee' => $data['tuition_fee'] ?? 0,
            'uniform_fee' => $data['uniform_fee'] ?? 0,
            'meals_fee' => $data['meals_fee'] ?? 0,
            'books_fee' => $data['books_fee'] ?? 0,
            'other_fee' => $data['other_fee'] ?? 0,
            'total_amount' => $totalAmount,
            'due_date' => $data['due_date'] ?? null,
            'paid_amount' => null,
            'paid_date' => null,
            'payment_month' => null,
            'status' => null,
        ]);

        $paidBy = $data['paid_by'] ?? $student->guardian_id ?? auth()->id();
        $invoiceMonth = $data['for_month'] ?? ($paidDate ? Carbon::parse($paidDate)->format('F') : ($data['due_date'] ? Carbon::parse($data['due_date'])->format('F') : Carbon::now()->format('F')));
        $invoiceYear = $data['for_year'] ?? ($paidDate ? Carbon::parse($paidDate)->format('Y') : ($data['due_date'] ? Carbon::parse($data['due_date'])->format('Y') : Carbon::now()->format('Y')));
        $discount = $data['discount'] ?? 0;
        $invoiceUuid = $data['invoice_uuid'] ?? Str::uuid()->toString();
        $invoiceMeta = [
            'student_id' => $student->id,
            'paid_by' => $paidBy,
            'for_month' => $invoiceMonth,
            'for_year' => $invoiceYear,
            'amount' => $totalAmount,
            'discount' => $discount,
            'total_amount' => $totalAmount - $discount,
            'payment_date' => null,
            'payment_method' => null,
            'reference_no' => null,
            'invoice_uuid' => $invoiceUuid,
            'stripe_payment_intent_id' => null,
            'stripe_charge_id' => null,
        ];

        if ($totalAmount > 0 && $paidAmount >= $totalAmount) {
            StudentInvoice::create(array_merge($invoiceMeta, [
                'paid_amount' => $totalAmount,
                'due_amount' => 0,
                'status' => 'paid',
                'payment_date' => $paymentDate,
                'payment_method' => 'cash',
            ]));
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            StudentInvoice::create(array_merge($invoiceMeta, [
                'paid_amount' => $paidAmount,
                'due_amount' => $totalAmount - $paidAmount,
                'status' => 'partial',
                'payment_date' => $paymentDate,
                'payment_method' => 'cash',
            ]));

            StudentInvoice::create(array_merge($invoiceMeta, [
                'amount' => $totalAmount - $paidAmount,
                'total_amount' => $totalAmount - $paidAmount,
                'paid_amount' => 0,
                'due_amount' => $totalAmount - $paidAmount,
                'status' => 'unpaid',
            ]));
        } else {
            StudentInvoice::create(array_merge($invoiceMeta, [
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'status' => 'unpaid',
            ]));
        }

        return $blueprint;
    }
}
