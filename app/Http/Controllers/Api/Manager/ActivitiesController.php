<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Invoice\SubmitManagerInvoicePaymentAction;
use App\Http\Controllers\Controller;
use App\Models\ManagerInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class ActivitiesController extends Controller
{
    public function __construct(
        protected SubmitManagerInvoicePaymentAction $submitPaymentAction,
    ) {
    }

    public function getInvoices(Request $request)
    {
        $invoices = ManagerInvoice::with(['institution', 'confirmedBy'])
            ->where('manager_id', auth()->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return $this->paginatedResponse(
            JsonResource::collection($invoices),
            'Invoices retrieved successfully'
        );
    }

    public function showInvoice(string $id)
    {
        $invoice = ManagerInvoice::with(['institution', 'confirmedBy'])
            ->where('manager_id', auth()->id())
            ->findOrFail($id);

        return $this->successResponse($invoice, 'Invoice retrieved successfully');
    }

    /**
     * Manager submits proof of an offline/manual payment (note, method,
     * proof file). Moves the invoice to pending_confirmation, awaiting the
     * admin's confirm step.
     */
    public function submitPayment(Request $request, string $id)
    {
        $data = $request->validate([
            'payment_note' => 'nullable|string|max:2000',
            'payment_method' => 'required|string|max:100',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        if ($request->hasFile('proof')) {
            $data['payment_proof'] = $request->file('proof')->store('invoice_payment_proofs', 'public');
        }

        try {
            $invoice = $this->submitPaymentAction->handle($data, $id, auth()->id());
            return $this->successResponse(
                $invoice->load(['institution', 'confirmedBy']),
                'Payment submitted, awaiting admin confirmation'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse(collect($e->errors())->flatten()->first(), 422);
        }
    }
}
