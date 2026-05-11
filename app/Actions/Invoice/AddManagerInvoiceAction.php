<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class AddManagerInvoiceAction
{
    public function handle($data)
    {
        $invoice = ManagerInvoice::create([
            'manager_id' => $data['manager_id'],
            'number_of_instutes' => $data['number_of_instutes'],
            'price_per_instute' => $data['price_per_instute'],
            'due_date' => $data['due_date'],
            'status' => $data['status'],
        ]);
        return $invoice;
    }
}