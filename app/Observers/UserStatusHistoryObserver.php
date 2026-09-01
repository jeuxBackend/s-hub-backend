<?php

namespace App\Observers;

use App\Models\StatusHistory;
use App\Models\User;

class UserStatusHistoryObserver
{
    public function created(User $user): void
    {
        StatusHistory::create([
            'statusable_type' => User::class,
            'statusable_id' => $user->id,
            'status' => (bool) $user->status,
            'changed_at' => $user->created_at ?? now(),
        ]);
    }

    public function updated(User $user): void
    {
        if (!$user->wasChanged('status')) {
            return;
        }

        StatusHistory::create([
            'statusable_type' => User::class,
            'statusable_id' => $user->id,
            'status' => (bool) $user->status,
            'changed_at' => now(),
        ]);
    }
}
