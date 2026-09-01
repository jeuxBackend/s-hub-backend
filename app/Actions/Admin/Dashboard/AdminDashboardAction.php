<?php

namespace App\Actions\Admin\Dashboard;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\Student;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AdminDashboardAction
{
    /**
     * Get Admin Dashboard Statistics
     */
    public function handle(): array
    {
        return Cache::remember('admin_dashboard_stats', now()->addMinutes(5), function () {
            $asOfLastMonth = now()->subMonth();

            $parentIds = User::where('role', UserRole::Parent->value)->pluck('id')->all();
            $teacherSchoolAdminIds = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])->pluck('id')->all();
            $principalIds = User::where('role', UserRole::Principal->value)->pluck('id')->all();

            return array_merge(
                $this->bucket('users', User::class, $asOfLastMonth,
                    User::where('status', true)->count(),
                    User::where('status', false)->count()),

                $this->bucket('institutions', Institution::class, $asOfLastMonth,
                    Institution::where('is_blocked', false)->count(),
                    Institution::where('is_blocked', true)->count()),

                $this->bucket('parents', User::class, $asOfLastMonth,
                    User::where('role', UserRole::Parent->value)->where('status', true)->count(),
                    User::where('role', UserRole::Parent->value)->where('status', false)->count(),
                    $parentIds),

                $this->bucket('teachers', User::class, $asOfLastMonth,
                    User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])->where('status', true)->count(),
                    User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])->where('status', false)->count(),
                    $teacherSchoolAdminIds),

                $this->bucket('principals', User::class, $asOfLastMonth,
                    User::where('role', UserRole::Principal->value)->where('status', true)->count(),
                    User::where('role', UserRole::Principal->value)->where('status', false)->count(),
                    $principalIds),

                $this->bucket('students', Student::class, $asOfLastMonth,
                    Student::where('status', true)->count(),
                    Student::where('status', false)->count()),

                ['last_updated' => now()->toDateTimeString()]
            );
        });
    }

    /**
     * Build the total/active/blocked counts (plus their "vs last month"
     * percentages) for one dashboard section, keyed with the given prefix.
     */
    private function bucket(
        string $prefix,
        string $statusableType,
        Carbon $asOfLastMonth,
        int $activeCount,
        int $blockedCount,
        ?array $idsFilter = null
    ): array {
        $activeLastMonth = StatusHistory::countAsOf($statusableType, $asOfLastMonth, true, $idsFilter);
        $blockedLastMonth = StatusHistory::countAsOf($statusableType, $asOfLastMonth, false, $idsFilter);

        $totalCount = $activeCount + $blockedCount;
        $totalLastMonth = $activeLastMonth + $blockedLastMonth;

        return [
            "total_{$prefix}" => $totalCount,
            "total_{$prefix}_change_percent" => $this->percentChange($totalCount, $totalLastMonth),
            "active_{$prefix}" => $activeCount,
            "active_{$prefix}_change_percent" => $this->percentChange($activeCount, $activeLastMonth),
            "blocked_{$prefix}" => $blockedCount,
            "blocked_{$prefix}_change_percent" => $this->percentChange($blockedCount, $blockedLastMonth),
        ];
    }

    private function percentChange(int $current, int $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
