<?php

namespace App\Actions\User;

use App\Models\User;
use App\Enums\UserRole;

class ChangeUserRoleAction
{
    public function handle(int $userId, string $role, $requester, $permissions = [], ?string $position = null)
    {
        $user = User::findOrFail($userId);

        if ($user->institution_id !== $requester->institution_id) {
            abort(403, 'You can only modify users within your own institution.');
        }

        // Handle stringified JSON from app side
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $permissions = $decoded;
            }
        }

        $user->role = UserRole::from($role);
        $user->permissions = is_array($permissions) ? $permissions : [];

        // Position only belongs to school admins; clear it for any other role.
        $user->position = $role === UserRole::SchoolAdmin->value ? $position : null;
        $user->save();

        return $user;
    }
}