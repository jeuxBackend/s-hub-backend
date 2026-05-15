<?php

namespace App\Actions\Dashboard;

use App\Models\StudentInvoice;
use App\Models\StudentAttendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetManagerDashboardStatsAction
{
    public function handle(): array
    {
        $manager = Auth::user();

        // High level counts
        $totalSchools = $manager->institutions()->count();
        $totalPrincipals = $manager->users()->where('role', 'principal')->count();
        $totalTeachers = $manager->users()->where('role', 'teacher')->count();
        $totalParents = $manager->users()->where('role', 'parent')->count();
        $totalStudents = $manager->students()->count();

        // Get array of student IDs to filter invoices and attendances
        $studentIds = $manager->students()->pluck('students.id')->toArray();

        // Total Fees collected
        $totalFees = StudentInvoice::whereIn('student_id', $studentIds)
            ->where('status', 'paid')
            ->sum('paid_amount');

        // Fees Overview (Jan - Dec for current year)
        $currentYear = Carbon::now()->year;
        $monthlyFees = StudentInvoice::select(
                DB::raw('SUM(paid_amount) as total'),
                DB::raw('MONTH(created_at) as month')
            )
            ->whereIn('student_id', $studentIds)
            ->where('status', 'paid')
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $feesOverview = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($months as $index => $month) {
            $feesOverview[] = [
                'month' => $month,
                'total' => (float) ($monthlyFees[$index + 1] ?? 0)
            ];
        }

        // Students Engagement (Mon - Sun for current week)
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $dailyAttendance = StudentAttendance::select(
                DB::raw('COUNT(id) as present_count'),
                DB::raw('DAYOFWEEK(date) as day_of_week') // 1 = Sunday, 2 = Monday in MySQL
            )
            ->whereIn('student_id', $studentIds)
            ->where('status', 'present')
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->groupBy('day_of_week')
            ->pluck('present_count', 'day_of_week')
            ->toArray();

        $engagement = [];
        // Map MySQL DAYOFWEEK (1=Sun, 2=Mon...7=Sat) to standard Mon-Sun display
        $days = [
            2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun'
        ];
        foreach ($days as $dayOfWeek => $dayName) {
            $engagement[] = [
                'day' => $dayName,
                'count' => (int) ($dailyAttendance[$dayOfWeek] ?? 0)
            ];
        }

        return [
            'total_schools' => $totalSchools,
            'total_principals' => $totalPrincipals,
            'total_teachers' => $totalTeachers,
            'total_parents' => $totalParents,
            'total_students' => $totalStudents,
            'total_fees' => (float) $totalFees,
            'fees_overview' => $feesOverview,
            'students_engagement' => $engagement,
        ];
    }
}
