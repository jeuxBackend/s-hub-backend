<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;
use Illuminate\Validation\ValidationException;

class ConfirmManagerInvoicePaymentAction
{
    /**
     * Admin confirms a manager's submitted payment. Only valid from
     * pending_confirmation, so a payment can't be confirmed twice or out
     * of order.
     */
    public function handle($id, int $confirmedByAdminId): ManagerInvoice
    {
        $invoice = ManagerInvoice::findOrFail($id);

        if ($invoice->status !== 'pending_confirmation') {
            throw ValidationException::withMessages([
                'status' => 'This invoice has no pending payment submission to confirm.',
            ]);
        }

        $invoice->update([
            'status' => 'paid',
            'confirmed_by' => $confirmedByAdminId,
            'confirmed_at' => now(),
        ]);

        return $invoice;
    }
}
