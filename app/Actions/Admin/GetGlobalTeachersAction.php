<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Enums\UserRole;

class GetGlobalTeachersAction
{
    public function handle(array $data = [])
    {
        $query = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->with('institution');

        if (!empty($data['name'])) {
            $query->where(function ($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                    ->orWhere('last_name', 'like', '%' . $data['name'] . '%')
                    ->orWhere('sur_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }
        if (!empty($data['phone_number'])) {
            $query->where('phone_number', 'like', '%' . $data['phone_number'] . '%');
        }
        if (!empty($data['role']) && in_array($data['role'], [UserRole::Teacher->value, UserRole::SchoolAdmin->value], true)) {
            $query->where('role', $data['role']);
        }
        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }
        if (!empty($data['manager_id'])) {
            $query->whereHas('institution', function ($q) use ($data) {
                $q->where('manager_id', $data['manager_id']);
            });
        }
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $query->where('status', filter_var($data['status'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $query->whereIn('institution_id', $data['institution_ids']);
        }

        return $query->orderBy('id', 'desc')->paginate($data['per_page'] ?? 20);
    }
}
