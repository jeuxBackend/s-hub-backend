<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;
use Illuminate\Validation\ValidationException;

class SubmitManagerInvoicePaymentAction
{
    /**
     * Manager submits proof of an offline/manual payment. Moves the invoice
     * into pending_confirmation, awaiting the admin's confirm step.
     */
    public function handle(array $data, $id, int $managerId): ManagerInvoice
    {
        $invoice = ManagerInvoice::where('manager_id', $managerId)->findOrFail($id);

        if (!in_array($invoice->status, ['pending', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This invoice cannot be submitted for payment in its current status.',
            ]);
        }

        $invoice->update([
            'payment_note' => $data['payment_note'] ?? null,
            'payment_method' => $data['payment_method'],
            'payment_proof' => $data['payment_proof'] ?? $invoice->payment_proof,
            'paid_submitted_at' => now(),
            'status' => 'pending_confirmation',
        ]);

        return $invoice;
    }
}
