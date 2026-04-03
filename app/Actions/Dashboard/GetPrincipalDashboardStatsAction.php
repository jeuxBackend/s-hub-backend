<?php

namespace App\Actions\Dashboard;

use App\Models\User;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;

class GetPrincipalDashboardStatsAction
{
    public function handle(): array
    {
        $principalId = Auth::id();
        $principal = Auth::user();

        return [
            'students_count' => Student::where('created_by', $principalId)->count(),
            'teachers_count' => User::where('role', 'teacher')->where('created_by', $principalId)->count(),
            'parents_count' => User::where('role', 'parent')->where('created_by', $principalId)->count(),
            'school_admins_count' => User::where('role', 'school_admin')->where('created_by', $principalId)->count(),
            'classrooms_count' => Classroom::where('institution_id', $principal->institution_id)->count(),
        ];
    }
}
