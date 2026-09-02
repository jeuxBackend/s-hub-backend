<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Invoice\AddManagerInvoiceAction;
use App\Actions\Invoice\DeleteInvoiceAction;
use App\Actions\Invoice\GetManagerInvoicesAction;
use App\Actions\Invoice\UpdateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\ManagerInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerInvoiceController extends Controller
{
    public function __construct(
        protected GetManagerInvoicesAction $getInvoicesAction,
        protected AddManagerInvoiceAction $createInvoiceAction,
        protected UpdateInvoiceAction $updateInvoiceAction,
        protected DeleteInvoiceAction $deleteInvoiceAction,
    ) {
    }

    /**
     * Display a listing of manager invoices
     */
    public function index(Request $request)
    {
        $invoices = $this->getInvoicesAction->handle($request->all());

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
            'number_of_instutes' => 'required|integer|min:1',
            'price_per_instute' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'due_date' => 'required|date|after:today',
            'status' => 'nullable|in:pending,paid,overdue',
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
        $invoice = ManagerInvoice::with(['manager', 'creator'])->findOrFail($id);
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
            'status' => 'sometimes|in:pending,paid,overdue',
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
}
