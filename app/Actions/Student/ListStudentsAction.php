<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Enums\UserRole;

class ListStudentsAction
{
    public function handle(array $filters = [])
    {
        $user = auth()->user();

        $query = Student::query()
            ->with([
                'classroom.teachers',
                'classroom',
                'guardian',
                'feeRecords',
                'attendanceRecords'
            ])
            ->latest();

        // 🧠 Restrict access unless admin/super_admin
        if (!in_array($user->role->value, ['admin', 'super_admin'])) {

            if ($user->isRole(UserRole::Teacher)) {
                $query->whereHas('classroom', function ($classroomQuery) use ($user) {
                    $classroomQuery->whereHas('teachers', function ($teacherQuery) use ($user) {
                        $teacherQuery->where('users.id', $user->id); // ✅ use users table
                    });
                });
            }

            if ($user->isRole(UserRole::Principal)) {
                $query->where('institution_id', $user->institution->id);
            }

            if ($user->isRole(UserRole::SchoolAdmin)) {
                $query->where('institution_id', $user->creator->institution->id);
            }

            if ($user->isRole(UserRole::Parent)) {
                $query->where('guardian_id', $user->id);
            }
        }

        // 🔍 Apply filters (for all roles)
        if (!empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (!empty($filters['term'])) {
            $query->where('term', $filters['term']);
        }

        return $query->paginate(10);
    }
}
