<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class GetManagerInvoicesAction
{
    public function handle($data)
    {
        return ManagerInvoice::where('manager_id', $data['manager_id'])->get();
    }
}