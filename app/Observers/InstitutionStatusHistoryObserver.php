<?php

namespace App\Observers;

use App\Models\Institution;
use App\Models\StatusHistory;

class InstitutionStatusHistoryObserver
{
    public function created(Institution $institution): void
    {
        StatusHistory::create([
            'statusable_type' => Institution::class,
            'statusable_id' => $institution->id,
            'status' => !$institution->is_blocked,
            'changed_at' => $institution->created_at ?? now(),
        ]);
    }

    public function updated(Institution $institution): void
    {
        if (!$institution->wasChanged('is_blocked')) {
            return;
        }

        StatusHistory::create([
            'statusable_type' => Institution::class,
            'statusable_id' => $institution->id,
            'status' => !$institution->is_blocked,
            'changed_at' => now(),
        ]);
    }
}
