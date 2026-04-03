<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;

class UserPolicy
{
    public function viewAny(User $actingUser): bool
    {
        return in_array($actingUser->role, [
            UserRole::Admin,
            UserRole::SubAdmin,
            UserRole::Principal,
            UserRole::SchoolAdmin,
        ]);
    }

    public function view(User $actingUser, User $targetUser): bool
    {
        return $this->canAccess($actingUser, $targetUser);
    }

public function update(User $actingUser, User $targetUser): bool
{
    // dd([
    //     'actingUser_id' => $actingUser->id,
    //     'actingUser_role' => $actingUser->role->value,
    //     'targetUser_id' => $targetUser->id,
    //     'targetUser_created_by' => $targetUser->created_by,
    //     'is_self' => $actingUser->id === $targetUser->id,
    //     'canAccess' => $this->canAccess($actingUser, $targetUser),
    // ]);

    return $actingUser->id === $targetUser->id
        || $this->canAccess($actingUser, $targetUser);
}

    public function delete(User $actingUser, User $targetUser): bool
    {
        // ❌ Users cannot delete themselves
        if ($actingUser->id === $targetUser->id) {
            return false;
        }

        return $this->canAccess($actingUser, $targetUser);
    }

    public function create(User $actingUser): bool
    {
        return in_array($actingUser->role, [
            UserRole::Admin,
            UserRole::SubAdmin,
            UserRole::Principal,
            UserRole::SchoolAdmin,
        ]);
    }

    protected function canAccess(User $actingUser, User $targetUser): bool
    {
        // ✅ Admins and SubAdmins can access anyone
        if (in_array($actingUser->role, [UserRole::Admin, UserRole::SubAdmin])) {
            return true;
        }

        // ✅ Principals or School Admins can access users they created
        return in_array($actingUser->role, [UserRole::Principal, UserRole::SchoolAdmin])
            && $targetUser->created_by === $actingUser->id;
    }
}
