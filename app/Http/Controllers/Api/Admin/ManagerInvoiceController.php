<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Invoice\AddManagerInvoiceAction;
use App\Actions\Invoice\ConfirmManagerInvoicePaymentAction;
use App\Actions\Invoice\DeleteInvoiceAction;
use App\Actions\Invoice\GetManagerInvoicesAction;
use App\Actions\Invoice\UpdateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\ManagerInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class ManagerInvoiceController extends Controller
{
    public function __construct(
        protected GetManagerInvoicesAction $getInvoicesAction,
        protected AddManagerInvoiceAction $createInvoiceAction,
        protected UpdateInvoiceAction $updateInvoiceAction,
        protected DeleteInvoiceAction $deleteInvoiceAction,
        protected ConfirmManagerInvoicePaymentAction $confirmPaymentAction,
    ) {
    }

    /**
     * Display a listing of manager invoices
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $invoices = $this->getInvoicesAction->handle($data);

        return $this->paginatedResponse(
            JsonResource::collection($invoices),
            'Invoices retrieved successfully'
        );
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'manager_id' => 'required|exists:admins,id',
            'institution_id' => 'nullable|exists:institutions,id',
            'number_of_instutes' => 'required|integer|min:1',
            'price_per_instute' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'due_date' => 'required|date|after:today',
            'status' => 'nullable|in:pending,pending_confirmation,paid,overdue',
        ]);

        $data['created_by'] = auth()->id();

        $invoice = $this->createInvoiceAction->handle($data);

        return $this->successResponse($invoice, 'Invoice created successfully', 201);
    }

    /**
     * Display the specified invoice
     */
    public function show(string $id)
    {
        $invoice = ManagerInvoice::with(['manager', 'creator', 'institution'])->findOrFail($id);

        $ids = auth()->user()->assignedInstitutionIds();
        if ($ids !== null) {
            $inScope = ($invoice->institution_id !== null && in_array($invoice->institution_id, $ids, true))
                || $invoice->manager->institutions()->whereIn('id', $ids)->exists();

            if (!$inScope) {
                abort(404);
            }
        }

        return $this->successResponse($invoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'number_of_instutes' => 'sometimes|integer|min:1',
            'price_per_instute' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|nullable|string|size:3',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,pending_confirmation,paid,overdue',
        ]);

        $invoice = $this->updateInvoiceAction->handle($data, $id);

        return $this->successResponse($invoice, 'Invoice updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->deleteInvoiceAction->handle($id);

        return $this->successResponse(null, 'Invoice deleted successfully');
    }

    /**
     * Confirm a manager's submitted payment, marking the invoice paid.
     */
    public function confirm(string $id)
    {
        try {
            $invoice = $this->confirmPaymentAction->handle($id, auth()->id());
            return $this->successResponse(
                $invoice->load(['manager', 'creator', 'institution', 'confirmedBy']),
                'Payment confirmed, invoice marked as paid'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse(collect($e->errors())->flatten()->first(), 422);
        }
    }
}
