<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;
use App\Models\StudentFee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateInvoiceReceiptPdfAction
{
    /**
     * Generate and return PDF for a student invoice receipt
     *
     * @param StudentInvoice $invoice
     * @return \Illuminate\Http\Response
     * @throws Throwable
     */
    public function handle(StudentInvoice $invoice)
    {
        // Load relationships
        $invoice->load(['student.classroom.institution']);

        $student = $invoice->student;
        $classroom = $student->classroom;
        $institution = $classroom->institution;

        // Get the student fee record for the invoice period
        $studentFee = StudentFee::where('student_id', $student->id)
            ->where('payment_month', $invoice->for_month)
            ->whereYear('created_at', $invoice->for_year)
            ->latest()
            ->first();

        // If no specific fee record found, get the latest one
        if (!$studentFee) {
            $studentFee = $student->feeRecords()->latest()->first();
        }

        // Calculate totals
        $totalAmount = ($studentFee->tuition_fee ?? 0) +
            ($studentFee->uniform_fee ?? 0) +
            ($studentFee->meals_fee ?? 0) +
            ($studentFee->books_fee ?? 0) +
            ($studentFee->other_fee ?? 0);

        $invoice_amount = $invoice->total_amount;
        $paidAmount = $invoice->paid_amount ?? 0;
        $dueAmount = $invoice->due_amount ?? ($invoice_amount - $paidAmount);

        // Set dates
        $issueDate = now()->subDays(1); // Issue date (yesterday)
        $dueDate = now()->addDays(30); // Due date (30 days from now)

        // If invoice has payment date, use it for calculations
        if ($invoice->payment_date) {
            $issueDate = \Carbon\Carbon::parse($invoice->payment_date);
            $dueDate = $issueDate->copy()->addDays(30);
        }

        // Render the Blade view
        $html = view('invoices.student_invoice_receipt', compact(
            'invoice',
            'student',
            'classroom',
            'institution',
            'studentFee',
            'totalAmount',
            'paidAmount',
            'dueAmount',
            'issueDate',
            'dueDate',
            'invoice_amount'
        ))->render();

        // Generate PDF
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        // Generate filename
        $filename = "invoice_receipt_{$invoice->invoice_uuid}.pdf";

        // Return PDF as download
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
