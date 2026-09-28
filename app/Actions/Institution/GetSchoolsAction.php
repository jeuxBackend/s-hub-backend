<?php

namespace App\Actions\Institution;

use App\Models\Institution;

class GetSchoolsAction
{
    public function handle(array $data = [])
    {
        $query = Institution::with('manager:id,first_name,last_name,sure_name,email')->with('category');

        if (!empty($data['name'])) {
            $query->where('name', 'like', '%' . $data['name'] . '%');
        }
        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }
        if (!empty($data['manager_id'])) {
            $query->where('manager_id', $data['manager_id']);
        }
        if (!empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (array_key_exists('is_blocked', $data) && $data['is_blocked'] !== null && $data['is_blocked'] !== '') {
            $query->where('is_blocked', filter_var($data['is_blocked'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $query->whereIn('id', $data['institution_ids']);
        }

        return $query->orderBy('id', 'desc')->paginate($data['per_page'] ?? 20);
    }
}
