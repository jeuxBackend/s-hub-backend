<?php

namespace App\Actions\Admin\Dashboard;

use App\Enums\GenderType;
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
     * Get Admin Dashboard Statistics, scoped to $institutionIds when given
     * (a restricted sub-admin's assigned schools), or platform-wide when
     * null (admin, manager, or an unrestricted sub-admin).
     */
    public function handle(?array $institutionIds = null): array
    {
        $cacheKey = 'admin_dashboard_stats_' . ($institutionIds === null ? 'all' : md5(implode(',', $institutionIds)));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($institutionIds) {
            $asOfLastMonth = now()->subMonth();

            $parentQuery = User::where('role', UserRole::Parent->value);
            $teacherQuery = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value]);
            $principalQuery = User::where('role', UserRole::Principal->value);
            $studentQuery = Student::query();
            $institutionQuery = Institution::query();

            if ($institutionIds !== null) {
                $parentQuery->whereHas('guardianStudents', fn($q) => $q->whereIn('institution_id', $institutionIds));
                $teacherQuery->whereIn('institution_id', $institutionIds);
                $principalQuery->whereIn('institution_id', $institutionIds);
                $studentQuery->whereIn('institution_id', $institutionIds);
                $institutionQuery->whereIn('id', $institutionIds);
            }

            $parentIds = (clone $parentQuery)->pluck('id')->all();
            $teacherSchoolAdminIds = (clone $teacherQuery)->pluck('id')->all();
            $principalIds = (clone $principalQuery)->pluck('id')->all();
            $studentIds = (clone $studentQuery)->pluck('id')->all();
            $scopedInstitutionIds = $institutionIds ?? (clone $institutionQuery)->pluck('id')->all();

            $userIdsInScope = $institutionIds === null
                ? null
                : array_values(array_unique(array_merge($parentIds, $teacherSchoolAdminIds, $principalIds)));

            return array_merge(
                $this->bucket('users', User::class, $asOfLastMonth,
                    $institutionIds === null ? User::where('status', true)->count() : User::whereIn('id', $userIdsInScope)->where('status', true)->count(),
                    $institutionIds === null ? User::where('status', false)->count() : User::whereIn('id', $userIdsInScope)->where('status', false)->count(),
                    $userIdsInScope),

                $this->bucket('institutions', Institution::class, $asOfLastMonth,
                    (clone $institutionQuery)->where('is_blocked', false)->count(),
                    (clone $institutionQuery)->where('is_blocked', true)->count(),
                    $institutionIds === null ? null : $scopedInstitutionIds),

                $this->bucket('parents', User::class, $asOfLastMonth,
                    (clone $parentQuery)->where('status', true)->count(),
                    (clone $parentQuery)->where('status', false)->count(),
                    $parentIds),

                $this->bucket('teachers', User::class, $asOfLastMonth,
                    (clone $teacherQuery)->where('status', true)->count(),
                    (clone $teacherQuery)->where('status', false)->count(),
                    $teacherSchoolAdminIds),

                $this->bucket('principals', User::class, $asOfLastMonth,
                    (clone $principalQuery)->where('status', true)->count(),
                    (clone $principalQuery)->where('status', false)->count(),
                    $principalIds),

                $this->bucket('students', Student::class, $asOfLastMonth,
                    (clone $studentQuery)->where('status', true)->count(),
                    (clone $studentQuery)->where('status', false)->count(),
                    $institutionIds === null ? null : $studentIds),

                $this->countriesStats($asOfLastMonth, $institutionIds),

                [
                    'students_by_gender' => $this->genderBreakdown(Student::class, null, $institutionIds),
                    'teachers_by_gender' => $this->genderBreakdown(User::class, [UserRole::Teacher->value, UserRole::SchoolAdmin->value], $institutionIds),
                ],

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

    /**
     * Distinct non-null institution regions, treated as "countries", plus
     * the change vs. how many distinct regions existed as of last month.
     * Scoped to $institutionIds when given.
     */
    private function countriesStats(Carbon $asOfLastMonth, ?array $institutionIds): array
    {
        $base = Institution::whereNotNull('region');
        if ($institutionIds !== null) {
            $base->whereIn('id', $institutionIds);
        }

        $currentCount = (clone $base)->distinct()->count('region');
        $lastMonthCount = (clone $base)->where('created_at', '<=', $asOfLastMonth)->distinct()->count('region');

        return [
            'total_countries' => $currentCount,
            'total_countries_change_percent' => $this->percentChange($currentCount, $lastMonthCount),
        ];
    }

    /**
     * Count male/female/other for the given model, optionally restricted to
     * a set of roles (for the shared `users` table) and/or a set of
     * institution ids.
     */
    private function genderBreakdown(string $modelClass, ?array $roles = null, ?array $institutionIds = null): array
    {
        $query = $modelClass::query();

        if ($roles !== null) {
            $query->whereIn('role', $roles);
        }

        if ($institutionIds !== null) {
            $query->whereIn('institution_id', $institutionIds);
        }

        $counts = (clone $query)->whereNotNull('gender')->groupBy('gender')->selectRaw('gender, count(*) as aggregate')->pluck('aggregate', 'gender');

        return [
            'male' => (int) ($counts[GenderType::Male->value] ?? 0),
            'female' => (int) ($counts[GenderType::Female->value] ?? 0),
            'other' => (int) ($counts[GenderType::Other->value] ?? 0),
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
