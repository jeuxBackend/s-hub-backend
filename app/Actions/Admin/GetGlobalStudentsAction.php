<?php

namespace App\Actions\Admin;

use App\Models\Student;

class GetGlobalStudentsAction
{
    public function handle(array $data = [])
    {
        $query = Student::with(['institution', 'classroom', 'guardian']);

        if (!empty($data['name'])) {
            $query->where(function($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('sur_name', 'like', '%' . $data['name'] . '%');
            });
        }
        if (!empty($data['registration_number'])) {
            $query->where('registration_number', 'like', '%' . $data['registration_number'] . '%');
        }
        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }
        if (!empty($data['classroom_id'])) {
            $query->where('classroom_id', $data['classroom_id']);
        }

        return $query->orderBy('id', 'desc')->get();
    }
}
