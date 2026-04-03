<?php

namespace App\Actions\User;

use App\Models\User;

class DeleteAccountAction
{
    public function handle(User $targetUser): void
    {
        // Authorization is handled via policy in controller
        $targetUser->tokens()->delete();
        $targetUser->delete();
    }
}
