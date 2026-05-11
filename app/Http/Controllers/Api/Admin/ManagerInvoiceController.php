<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Invoice\AddManagerInvoiceAction;
use App\Actions\Invoice\DeleteInvoiceAction;
use App\Actions\Invoice\GetManagerInvoicesAction;
use App\Actions\Invoice\UpdateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\ManagerInvoice;
use Illuminate\Http\Request;

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
        $managerId = $request->query('manager_id');
        $invoices = $this->getInvoicesAction->handle($managerId);

        return $this->successResponse($invoices, 'Invoices retrieved successfully');
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
            'due_date' => 'required|date|after:today',
            'status' => 'nullable|in:pending,paid,overdue',
        ]);

        $invoice = $this->createInvoiceAction->handle($data);

        return $this->successResponse($invoice, 'Invoice created successfully', 201);
    }

    /**
     * Display the specified invoice
     */
    public function show(string $id)
    {
        $invoice = ManagerInvoice::with('manager')->findOrFail($id);
        return $this->successResponse($invoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invoice = $this->updateInvoiceAction->handle($request, $id);

        return $this->successResponse($invoice, 'Invoice updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $invoice = ManagerInvoice::destroy($id);

        return $this->successResponse($invoice, 'Invoice deleted successfully');
    }
}
