<?php

namespace App\Actions\Admin\Dashboard;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminDashboardAction
{
    /**
     * Get Admin Dashboard Statistics
     */
    public function handle(): array
    {
        return Cache::remember('admin_dashboard_stats', now()->addMinutes(5), function () {

            $teachersCount = User::where('role', UserRole::Teacher->value)->count();
            $parentsCount = User::where('role', UserRole::Parent->value)->count();
            $schoolAdminsCount = User::where('role', UserRole::SchoolAdmin->value)->count();

            $studentsCount = Student::count();
            $institutionsCount = Institution::count();

            $totalUsers = $studentsCount + $teachersCount + $parentsCount + $schoolAdminsCount;

            return [
                'total_institutions' => $institutionsCount,
                'total_students' => $studentsCount,
                'total_teachers' => $teachersCount,
                'total_parents' => $parentsCount,
                'total_school_admins' => $schoolAdminsCount,
                'total_users' => $totalUsers,

                'recent_institutions' => Institution::latest()->take(5)->get(['id', 'name', 'created_at']),
                'recent_students' => Student::latest()->take(5)->get(['id', 'name', 'institution_id', 'created_at']),

                'last_updated' => now()->toDateTimeString(),
            ];
        });
    }
}