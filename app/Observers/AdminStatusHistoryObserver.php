<?php

namespace App\Observers;

use App\Models\Admin;
use App\Models\StatusHistory;

class AdminStatusHistoryObserver
{
    public function created(Admin $admin): void
    {
        StatusHistory::create([
            'statusable_type' => Admin::class,
            'statusable_id' => $admin->id,
            'status' => $admin->status === 'active',
            'changed_at' => $admin->created_at ?? now(),
        ]);
    }

    public function updated(Admin $admin): void
    {
        if (!$admin->wasChanged('status')) {
            return;
        }

        StatusHistory::create([
            'statusable_type' => Admin::class,
            'statusable_id' => $admin->id,
            'status' => $admin->status === 'active',
            'changed_at' => now(),
        ]);
    }
}

