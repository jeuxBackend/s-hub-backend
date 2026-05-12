<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Enums\UserRole;

class GetManagerAction
{
    public function handle(array $data = [])
    {
        $query = Admin::select(['id', 'first_name', 'sure_name', 'email', 'role', 'status'])
            ->where('role', AdminRole::Manager)
            ->withCount([
                'institutions as total_schools',
                'students as total_student',
                'users as total_teachers' => function ($query) {
                    $query->where('role', UserRole::Teacher->value);
                },
                'users as total_school_sub_admin' => function ($query) {
                    $query->where('is_school_admin', true);
                }
            ]);

        if (!empty($data['name'])) {
            $query->where(function($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('sure_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }

        return $query->orderBy('first_name', 'desc')->get();
    }
}