<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Enums\UserRole;

class GetGlobalTeachersAction
{
    public function handle(array $data = [])
    {
        $query = User::where('role', UserRole::Teacher->value)
            ->with('institution');

        if (!empty($data['name'])) {
            $query->where(function($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('sur_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }
        if (!empty($data['phone_number'])) {
            $query->where('phone_number', 'like', '%' . $data['phone_number'] . '%');
        }
        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }

        return $query->orderBy('id', 'desc')->get();
    }
}
