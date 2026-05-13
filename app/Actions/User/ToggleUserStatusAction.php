<?php

namespace App\Actions\User;

use App\Models\User;

class ToggleUserStatusAction
{
    public function handle($id)
    {
        $user = User::findOrFail($id);

        // Toggle status
        $user->status = !$user->status;

        $user->save();

        return $user;
    }
}