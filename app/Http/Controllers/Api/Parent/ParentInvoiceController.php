<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Actions\Parent\ListParentInvoicesAction;
use App\Actions\Parent\CreateParentPaymentIntentAction;
use App\Actions\Parent\ConfirmParentPaymentAction;
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
        try {
            $parentId = auth()->id();
            $invoices = $action->handle($parentId, $request);

            return $this->successResponse($invoices, 'Invoices retrieved successfully.');
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
}
