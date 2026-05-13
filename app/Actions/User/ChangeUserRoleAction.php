<?php

namespace App\Actions\User;

use App\Models\User;
use App\Enums\UserRole;

class ChangeUserRoleAction
{
    public function handle(int $userId, string $role, $requester)
    {
        $user = User::findOrFail($userId);

        if ($user->institution_id !== $requester->institution_id) {
            abort(403, 'You can only modify users within your own institution.');
        }

        $user->role = UserRole::from($role);
        $user->save();

        return $user;
    }
}