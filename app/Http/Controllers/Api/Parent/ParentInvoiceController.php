<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Actions\Parent\ListParentInvoicesAction;
use App\Actions\Parent\CreateParentPaymentIntentAction;
use App\Actions\Parent\ConfirmParentPaymentAction;
use App\Actions\StudentInvoice\GenerateInvoiceReceiptPdfAction;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Throwable;

class ParentInvoiceController extends Controller
{
    /**
     * List all child invoices for the parent
     */
    public function index(Request $request, ListParentInvoicesAction $action)
    {
        $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        try {
            $parentId = auth()->id();
            $studentId = $request->query('student_id');

            if ($studentId) {
                Student::where('id', $studentId)
                    ->where('guardian_id', $parentId)
                    ->firstOrFail();
            }

            $invoices = $action->handle($parentId, $request);
            $response = ['invoices' => $invoices];

            if ($studentId) {
                $grouped = $invoices->groupBy(fn($invoice) => $invoice->invoice_uuid ?: 'inv_'.$invoice->id);
                $totalPaid = 0.0;
                $totalOwing = 0.0;

                foreach ($grouped as $group) {
                    $groupTotal = (float) $group->max('total_amount');
                    $groupPaid = (float) $group->sum('paid_amount');
                    $groupOwing = max(0.0, $groupTotal - $groupPaid);

                    $totalPaid += $groupPaid;
                    $totalOwing += $groupOwing;
                }

                $studentFee = StudentFee::where('student_id', $studentId)->latest()->first();

                $response['summary'] = [
                    'student_id' => $studentId,
                    'total_paid' => $totalPaid,
                    'total_owing' => $totalOwing,
                    'student_fee' => $studentFee,
                ];
            }

            return $this->successResponse($response, 'Invoices retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Show detailed invoice info
     */
    public function show($id)
    {
        try {
            $parentId = auth()->id();
            $invoice = StudentInvoice::with(['student.institution.manager'])
                ->whereHas('student', function ($q) use ($parentId) {
                    $q->where('guardian_id', $parentId);
                })
                ->findOrFail($id);

            return $this->successResponse($invoice, 'Invoice details retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Generate Stripe Payment Intent (Destination Charge to Manager)
     */
    public function pay($id, CreateParentPaymentIntentAction $action)
    {
        try {
            $parentId = auth()->id();
            $paymentDetails = $action->handle($parentId, $id);

            return $this->successResponse($paymentDetails, 'Payment intent created successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Confirm stripe payment synchronously (Fallback)
     */
    public function confirm(Request $request, $id, ConfirmParentPaymentAction $action)
    {
        $request->validate([
            'payment_intent_id' => ['required', 'string'],
        ]);

        try {
            $parentId = auth()->id();
            $invoice = $action->handle(
                $parentId,
                $id,
                $request->input('payment_intent_id')
            );

            return $this->successResponse($invoice, 'Payment confirmed and invoice marked as paid.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Download invoice receipt as PDF
     */
    public function downloadReceipt($id, GenerateInvoiceReceiptPdfAction $action)
    {
        try {
            $parentId = auth()->id();
            
            // Verify parent owns this invoice (through student relationship)
            $invoice = StudentInvoice::whereHas('student', function ($q) use ($parentId) {
                $q->where('guardian_id', $parentId);
            })->findOrFail($id);

            return $action->handle($invoice);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
