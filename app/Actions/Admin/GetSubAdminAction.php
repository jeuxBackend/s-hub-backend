<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;

class GetSubAdminAction
{
    public function handle(array $data = [])
    {
        $query = Admin::select(['id', 'first_name', 'last_name', 'sure_name', 'email', 'role', 'status', 'region', 'profile_image'])
            ->where('role', AdminRole::SubAdmin);

        if (!empty($data['name'])) {
            $query->where(function($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('last_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('sure_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }
        if (!empty($data['region'])) {
            $query->whereJsonContains('region', $data['region']);
        }

        return $query->orderBy('first_name', 'desc')->get();
    }
}
