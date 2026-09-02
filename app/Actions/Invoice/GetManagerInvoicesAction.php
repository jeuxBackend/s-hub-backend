<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class GetManagerInvoicesAction
{
    public function handle(array $data = [])
    {
        $query = ManagerInvoice::with(['manager', 'creator'])->latest();

        if (!empty($data['manager_id'])) {
            $query->where('manager_id', $data['manager_id']);
        }
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $query->paginate($data['per_page'] ?? 20);
    }
}
