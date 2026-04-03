<?php

namespace App\Actions\User;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Collection;

class ListUsersAction
{
    public function handle(array $filters, User $requester): Collection
    {
        $query = User::query();

        // 🔒 Restrict access to created users if not Admin or SubAdmin
        if (in_array($requester->role->value, [
            UserRole::Principal->value,
            UserRole::SchoolAdmin->value,
        ])) {
            $query->where('created_by', $requester->id);
        }

        // 🔍 Apply filters
        $query
            ->when(!empty($filters['role']), fn($q) => $q->where('role', UserRole::from($filters['role'])))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['name']), fn($q) => $q->whereRaw("CONCAT(first_name, ' ', sur_name) LIKE ?", ["%{$filters['name']}%"]))
            ->when(!empty($filters['email']), fn($q) => $q->where('email', 'like', "%{$filters['email']}%"))
            ->when(!empty($filters['phone']), fn($q) => $q->where('phone_number', 'like', "%{$filters['phone']}%"));

        return $query->latest()->get();
    }
}
