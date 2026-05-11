<?php

namespace App\Actions\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Enums\UserRole;

class GetManagerAction
{
    public function handle()
    {
        $managers = Admin::select(['id', 'name', 'email', 'role', 'status'])
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
            ])
            ->orderBy('name', 'desc')
            ->get();

        return $managers;
    }
}