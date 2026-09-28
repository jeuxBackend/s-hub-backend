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
        $query = Admin::select(['id', 'first_name', 'last_name', 'sure_name', 'email', 'phone_number', 'role', 'status', 'profile_image'])
            ->where('role', AdminRole::Manager)
            ->withCount([
                'institutions as total_schools',
                'students as total_student',
                'users as total_teachers' => function ($query) {
                    $query->where('role', UserRole::Teacher->value);
                },
                'users as total_school_sub_admin' => function ($query) {
                    $query->where('role', UserRole::SchoolAdmin->value);
                }
            ]);

        if (!empty($data['name'])) {
            $query->where(function ($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                    ->orWhere('last_name', 'like', '%' . $data['name'] . '%')
                    ->orWhere('sure_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('sure_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_number', 'like', '%' . $search . '%');
            });
        }
        if (!empty($data['category_id'])) {
            $query->whereHas('institutions', function ($q) use ($data) {
                $q->where('category_id', $data['category_id']);
            });
        }
        if (isset($data['status']) && $data['status'] !== '') {
            $isActive = in_array(strtolower((string) $data['status']), ['active', '1', 'true'], true);
            $query->where('status', $isActive ? 'active' : 'inactive');
        }
        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $query->whereHas('institutions', function ($q) use ($data) {
                $q->whereIn('id', $data['institution_ids']);
            });
        }

        return $query->orderBy('first_name', 'desc')->paginate($data['per_page'] ?? 20);
    }
}