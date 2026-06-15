<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentWithInvoicesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'sur_name' => $this->sur_name,
            'full_name' => trim($this->first_name . ' ' . $this->sur_name),
            'profile_picture' => $this->profile_picture,
            'student_phone_number' => $this->student_phone_number,
            'gender' => $this->gender->value ?? null,
            'dob' => $this->dob?->toDateString(),
            'age' => $this->age,
            'religion' => $this->religion,
            'term' => $this->term->value ?? null,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'address' => $this->address,
            'institution_id' => $this->institution_id,
            'classroom_id' => $this->classroom_id,
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'institution' => new InstitutionResource($this->whenLoaded('institution')),

            // Guardian/Parent Information
            'guardian' => [
                'id' => $this->guardian?->id,
                'first_name' => $this->guardian?->first_name,
                'last_name' => $this->guardian?->sur_name,
                'full_name' => $this->guardian?->full_name,
                'email' => $this->guardian?->email,
                'phone_number' => $this->guardian?->phone_number,
                'profile_picture' => $this->guardian?->profile_picture,
                'role' => $this->guardian?->role->value,
            ],

            // Student Invoices
            'invoices' => $this->studentInvoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'for_month' => $invoice->for_month,
                    'for_year' => $invoice->for_year,
                    'amount' => $invoice->amount,
                    'discount' => $invoice->discount,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'due_amount' => $invoice->due_amount,
                    'status' => $invoice->status,
                    'payment_date' => $invoice->payment_date,
                    'payment_method' => $invoice->payment_method,
                    'reference_no' => $invoice->reference_no,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ];
            }),

            // 'invoice_summary' => [
            //     'total_invoices' => $this->studentInvoices->count(),
            //     'total_amount' => $this->studentInvoices->sum('total_amount'),
            //     'total_paid' => $this->studentInvoices->sum('paid_amount'),
            //     'total_due' => $this->studentInvoices->sum('due_amount'),
            //     'paid_count' => $this->studentInvoices->where('status', 'paid')->count(),
            //     'partial_count' => $this->studentInvoices->where('status', 'partial')->count(),
            //     'unpaid_count' => $this->studentInvoices->where('status', 'unpaid')->count(),
            // ],

            'student_fees' => $this->whenLoaded('feeRecords') ? $this->feeRecords->map(function ($fee) {
                return [
                    'id' => $fee->id,
                    'student_id' => $fee->student_id,
                    'class_id' => $fee->class_id,
                    'tuition_fee' => $fee->tuition_fee,
                    'uniform_fee' => $fee->uniform_fee,
                    'meals_fee' => $fee->meals_fee,
                    'books_fee' => $fee->books_fee,
                    'other_fee' => $fee->other_fee,
                    'paid_amount' => $fee->paid_amount,
                    'total_amount' => $fee->total_amount,
                    'due_date' => $fee->due_date,
                    'paid_date' => $fee->paid_date,
                    'payment_month' => $fee->payment_month,
                    'status' => $fee->status,
                    'created_at' => $fee->created_at,
                    'updated_at' => $fee->updated_at,
                ];
            }) : [],

            // 'student_fee_summary' => $this->whenLoaded('feeRecords') ? [
            //     'total_fees' => $this->feeRecords->count(),
            //     'total_tuition' => $this->feeRecords->sum('tuition_fee'),
            //     'total_uniform' => $this->feeRecords->sum('uniform_fee'),
            //     'total_meals' => $this->feeRecords->sum('meals_fee'),
            //     'total_books' => $this->feeRecords->sum('books_fee'),
            //     'total_other' => $this->feeRecords->sum('other_fee'),
            //     'total_paid' => $this->feeRecords->sum('paid_amount'),
            //     'total_amount' => $this->feeRecords->sum('total_amount'),
            // ] : [],
        ];
    }
}
