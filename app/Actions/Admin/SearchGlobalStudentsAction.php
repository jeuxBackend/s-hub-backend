<?php

namespace App\Actions\Admin;

use App\Models\Student;

class SearchGlobalStudentsAction
{
    public function handle(array $data = [])
    {
        $query = Student::with(['institution', 'classroom', 'guardian']);

        if (!empty($data['search'])) {
            $search = trim($data['search']);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('sur_name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('student_phone_number', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $query->where('status', filter_var($data['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($data['gender'])) {
            $query->where('gender', $data['gender']);
        }

        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }

        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $query->whereIn('institution_id', $data['institution_ids']);
        }

        return $query->orderBy('id', 'desc')->paginate($data['per_page'] ?? 20);
    }
}
