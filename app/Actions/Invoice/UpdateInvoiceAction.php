<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class UpdateInvoiceAction
{
    public function handle(array $data, $id): ManagerInvoice
    {
        $invoice = ManagerInvoice::findOrFail($id);

        $numberOfInstitutes = $data['number_of_instutes'] ?? $invoice->number_of_instutes;
        $pricePerInstitute = $data['price_per_instute'] ?? $invoice->price_per_instute;

        $invoice->update([
            'number_of_instutes' => $numberOfInstitutes,
            'price_per_instute' => $pricePerInstitute,
            'currency' => $data['currency'] ?? $invoice->currency,
            'total_amount' => $numberOfInstitutes * $pricePerInstitute,
            'due_date' => $data['due_date'] ?? $invoice->due_date,
            'status' => $data['status'] ?? $invoice->status,
        ]);

        return $invoice;
    }
}
