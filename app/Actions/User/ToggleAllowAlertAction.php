<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ToggleAllowAlertAction
{
    public function handle(User $user): User
    {
        Gate::authorize('update', $user);

        $user->update([
            'allow_alert' => !$user->allow_alert,
        ]);

        return $user->refresh();
    }
}
