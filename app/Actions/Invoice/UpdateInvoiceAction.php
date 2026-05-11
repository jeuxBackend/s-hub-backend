<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class UpdateInvoiceAction
{
    public function handle($request, $id)
    {
        $invoice = ManagerInvoice::find($id);
        $invoice->update(
            [
                'number_of_instutes' => $request->number_of_instutes ?? $invoice->number_of_instutes,
                'price_per_instute' => $request->price_per_instute ?? $invoice->price_per_instute,
                'due_date' => $request->due_date ?? $invoice->due_date,
                'status' => $request->status ?? $invoice->status,
            ]
        );
        return $invoice;
    }
}