<?php

namespace App\Actions\Invoice;

use App\Models\ManagerInvoice;

class GetManagerInvoicesAction
{
    public function handle(array $data = [])
    {
        $query = ManagerInvoice::with(['manager', 'creator', 'institution', 'confirmedBy'])->latest();

        if (!empty($data['manager_id'])) {
            $query->where('manager_id', $data['manager_id']);
        }
        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $ids = $data['institution_ids'];
            $query->where(function ($q) use ($ids) {
                $q->whereIn('institution_id', $ids)
                    ->orWhereHas('manager.institutions', function ($mq) use ($ids) {
                        $mq->whereIn('id', $ids);
                    });
            });
        }

        return $query->paginate($data['per_page'] ?? 20);
    }
}
