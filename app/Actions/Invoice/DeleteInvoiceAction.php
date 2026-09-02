<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class DeleteInvoiceAction
{
    public function handle($id): bool
    {
        $invoice = ManagerInvoice::findOrFail($id);

        return (bool) $invoice->delete();
    }
}
