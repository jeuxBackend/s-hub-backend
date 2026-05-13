<?php

namespace App\Actions\Institution;

use App\Models\Institution;

class GetSchoolsAction
{
    public function handle(array $data = [])
    {
        $query = Institution::with('manager:id,first_name,sure_name,email')->with('category');

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

        return $query->get();
    }
}
