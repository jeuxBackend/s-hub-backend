<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ToggleNotificationAction
{
    public function handle(User $user): User
    {
        Gate::authorize('update', $user);
        $user->update([
            'notifications_enabled' => !$user->notifications_enabled,
        ]);

        return $user->refresh();
    }
}
