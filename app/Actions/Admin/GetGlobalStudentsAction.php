<?php

namespace App\Actions\Admin;

use App\Models\Student;

class GetGlobalStudentsAction
{
    public function handle(array $data = [])
    {
        $query = Student::with([
            'institution',
            'classroom',
            'guardian',
            'guardian.authorizedPickup',
            'studentInvoices',
            'studentGrades',
        ]);

        if (!empty($data['name'])) {
            $query->where(function($q) use ($data) {
                $q->where('first_name', 'like', '%' . $data['name'] . '%')
                  ->orWhere('last_name', 'like', '%' . $data['name'] . '%')
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
        if (!empty($data['manager_id'])) {
            $query->whereHas('institution', function ($q) use ($data) {
                $q->where('manager_id', $data['manager_id']);
            });
        }
        if (array_key_exists('institution_ids', $data) && $data['institution_ids'] !== null) {
            $query->whereIn('institution_id', $data['institution_ids']);
        }

        return $query->orderBy('id', 'desc')->get()->map(function (Student $student) {
            $yearMarks = $student->studentGrades->where('type', 'years_marks');
            $perfTotal = $yearMarks->sum('total');
            $student->setAttribute(
                'performance_percentage',
                $perfTotal > 0 ? round(($yearMarks->sum('score') / $perfTotal) * 100, 2) : 0
            );
            $student->setAttribute('total_paid', $student->studentInvoices->sum('paid_amount'));
            $student->setAttribute('total_due', $student->studentInvoices->sum('due_amount'));

            return $student;
        });
    }
}
