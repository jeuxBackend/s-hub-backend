<?php

namespace App\Actions\Dashboard;

use App\Models\Student;
use App\Models\StatusHistory;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class GetManagerDashboardStatsAction
{
    /**
     * @param string|null $region Restrict to institutions in this region only.
     * @param string $feesPeriod  7D | 1M | 1Y — granularity for fees_overview.
     * @param string $engagementPeriod 7D | 1M | 1Y — granularity for students_engagement.
     */
    public function handle(?string $region = null, string $feesPeriod = '7D', string $engagementPeriod = '7D'): array
    {
        $manager = Auth::user();
        $asOfLastMonth = Carbon::now()->subMonth();

        $institutionsQuery = $manager->institutions();
        if (!empty($region)) {
            $institutionsQuery->where('region', $region);
        }
        $institutions = $institutionsQuery->get(['id', 'created_at']);
        $institutionIds = $institutions->pluck('id')->all();

        $studentIds = Student::whereIn('institution_id', $institutionIds)->pluck('id')->all();
        $principalIds = User::whereIn('institution_id', $institutionIds)->where('role', 'principal')->pluck('id')->all();
        $teacherIds = User::whereIn('institution_id', $institutionIds)->whereIn('role', ['teacher', 'school-admin'])->pluck('id')->all();
        $parentIds = User::whereIn('institution_id', $institutionIds)->where('role', 'parent')->pluck('id')->all();
        $allUserIds = array_values(array_unique(array_merge($principalIds, $teacherIds, $parentIds)));

        // Same three-way breakdown the principal side already shows (owed by
        // parents / actually paid / still owing), just aggregated across
        // every school this manager owns.
        $totalFees = StudentInvoice::whereIn('student_id', $studentIds)->sum('total_amount');
        $totalFeesLastMonth = StudentInvoice::whereIn('student_id', $studentIds)
            ->where('created_at', '<=', $asOfLastMonth)
            ->sum('total_amount');

        $paidTotalAmount = StudentInvoice::whereIn('student_id', $studentIds)->sum('paid_amount');
        $paidTotalAmountLastMonth = StudentInvoice::whereIn('student_id', $studentIds)
            ->where('created_at', '<=', $asOfLastMonth)
            ->sum('paid_amount');

        $oweSubscriptionFees = StudentInvoice::whereIn('student_id', $studentIds)->sum('due_amount');
        $oweSubscriptionFeesLastMonth = StudentInvoice::whereIn('student_id', $studentIds)
            ->where('created_at', '<=', $asOfLastMonth)
            ->sum('due_amount');

        $totalUsers = count($allUserIds);
        $activeUsers = User::whereIn('id', $allUserIds)->where('status', true)->count();
        $blockedUsers = User::whereIn('id', $allUserIds)->where('status', false)->count();

        return array_merge(
            [
                'total_schools' => $institutions->count(),
                'total_schools_change_percent' => $this->percentChange(
                    $institutions->count(),
                    $institutions->where('created_at', '<=', $asOfLastMonth)->count()
                ),
            ],
            $this->statCard('total_principals', count($principalIds), $asOfLastMonth, $principalIds),
            $this->statCard('total_teachers', count($teacherIds), $asOfLastMonth, $teacherIds),
            $this->statCard('total_parents', count($parentIds), $asOfLastMonth, $parentIds),
            $this->statCard('total_students', count($studentIds), $asOfLastMonth, $studentIds, Student::class),
            [
                'total_fees' => (float) $totalFees,
                'total_fees_change_percent' => $this->percentChange((float) $totalFees, (float) $totalFeesLastMonth),

                'paid_total_amount' => (float) $paidTotalAmount,
                'paid_total_amount_change_percent' => $this->percentChange((float) $paidTotalAmount, (float) $paidTotalAmountLastMonth),

                'owe_subscription_fees' => (float) $oweSubscriptionFees,
                'owe_subscription_fees_change_percent' => $this->percentChange((float) $oweSubscriptionFees, (float) $oweSubscriptionFeesLastMonth),

                'total_users' => $totalUsers,
                'total_users_change_percent' => $this->percentChange($totalUsers, $this->userCountAsOf($allUserIds, $asOfLastMonth)),

                'total_active_users' => $activeUsers,
                'total_active_users_change_percent' => $this->percentChange(
                    $activeUsers,
                    StatusHistory::countAsOf(User::class, $asOfLastMonth, true, $allUserIds)
                ),

                'blocked_users' => $blockedUsers,
                'blocked_users_change_percent' => $this->percentChange(
                    $blockedUsers,
                    StatusHistory::countAsOf(User::class, $asOfLastMonth, false, $allUserIds)
                ),
            ],
            [
                'fees_overview' => $this->feesOverview($studentIds, $feesPeriod),
                'students_engagement' => $this->studentsEngagement($studentIds, $engagementPeriod),
            ]
        );
    }

    /**
     * Total + "vs last month" percent for one role/model bucket.
     */
    private function statCard(string $key, int $current, Carbon $asOfLastMonth, array $idsFilter, string $modelClass = User::class): array
    {
        $lastMonth = $this->countAsOf($modelClass, $asOfLastMonth, $idsFilter);

        return [
            $key => $current,
            "{$key}_change_percent" => $this->percentChange($current, $lastMonth),
        ];
    }

    private function countAsOf(string $modelClass, Carbon $asOf, array $idsFilter): int
    {
        return StatusHistory::countAsOf($modelClass, $asOf, true, $idsFilter)
            + StatusHistory::countAsOf($modelClass, $asOf, false, $idsFilter);
    }

    private function userCountAsOf(array $idsFilter, Carbon $asOf): int
    {
        return $this->countAsOf(User::class, $asOf, $idsFilter);
    }

    private function percentChange(int|float $current, int|float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Fee collection totals grouped per the requested period.
     */
    private function feesOverview(array $studentIds, string $period): array
    {
        return match (strtoupper($period)) {
            '1M' => $this->weeklyAmountSeries(StudentInvoice::class, 'payment_date', 'paid_amount', $studentIds),
            '1Y' => $this->monthlyAmountSeries(StudentInvoice::class, 'payment_date', 'paid_amount', $studentIds),
            default => $this->dailyAmountSeries(
                StudentInvoice::class,
                'payment_date',
                'paid_amount',
                $studentIds,
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ),
        };
    }

    /**
     * "Present" attendance counts grouped per the requested period.
     */
    private function studentsEngagement(array $studentIds, string $period): array
    {
        return match (strtoupper($period)) {
            '1M' => $this->weeklyCountSeries(StudentAttendance::class, 'date', $studentIds),
            '1Y' => $this->monthlyCountSeries(StudentAttendance::class, 'date', $studentIds),
            default => $this->dailyCountSeries(
                StudentAttendance::class,
                'date',
                $studentIds,
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ),
        };
    }

    private function dailyAmountSeries(string $modelClass, string $dateColumn, string $sumColumn, array $studentIds, Carbon $start, Carbon $end): array
    {
        $rows = $modelClass::query()
            ->selectRaw("DATE({$dateColumn}) as day, SUM({$sumColumn}) as total")
            ->whereIn('student_id', $studentIds)
            ->where('status', 'paid')
            ->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()])
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'label' => $cursor->format('D'),
                'date' => $key,
                'total' => (float) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Current calendar month, bucketed into 4 week-of-month groups
     * (days 1-7, 8-14, 15-21, 22-end), labeled W1-W4.
     */
    private function weeklyAmountSeries(string $modelClass, string $dateColumn, string $sumColumn, array $studentIds): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $rows = $modelClass::query()
            ->select([$dateColumn, $sumColumn])
            ->whereIn('student_id', $studentIds)
            ->where('status', 'paid')
            ->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()])
            ->get();

        $weeks = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->{$dateColumn})->day;
            $weekNumber = min(4, intdiv($day - 1, 7) + 1);
            $weeks[$weekNumber] += (float) $row->{$sumColumn};
        }

        $series = [];
        foreach ($weeks as $weekNumber => $total) {
            $series[] = ['label' => "W{$weekNumber}", 'total' => round($total, 2)];
        }

        return $series;
    }

    private function monthlyAmountSeries(string $modelClass, string $dateColumn, string $sumColumn, array $studentIds): array
    {
        $currentYear = Carbon::now()->year;
        $rows = $modelClass::query()
            ->selectRaw("MONTH({$dateColumn}) as month, SUM({$sumColumn}) as total")
            ->whereIn('student_id', $studentIds)
            ->where('status', 'paid')
            ->whereYear($dateColumn, $currentYear)
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $series = [];
        foreach ($months as $index => $month) {
            $series[] = [
                'label' => $month,
                'total' => (float) ($rows[$index + 1] ?? 0),
            ];
        }

        return $series;
    }

    private function dailyCountSeries(string $modelClass, string $dateColumn, array $studentIds, Carbon $start, Carbon $end): array
    {
        $rows = $modelClass::query()
            ->selectRaw("DATE({$dateColumn}) as day, COUNT(id) as aggregate")
            ->whereIn('student_id', $studentIds)
            ->where('status', 'present')
            ->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()])
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'label' => $cursor->format('D'),
                'date' => $key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Current calendar month, bucketed into 4 week-of-month groups
     * (days 1-7, 8-14, 15-21, 22-end), labeled W1-W4.
     */
    private function weeklyCountSeries(string $modelClass, string $dateColumn, array $studentIds): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $rows = $modelClass::query()
            ->select([$dateColumn])
            ->whereIn('student_id', $studentIds)
            ->where('status', 'present')
            ->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()])
            ->get();

        $weeks = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->{$dateColumn})->day;
            $weekNumber = min(4, intdiv($day - 1, 7) + 1);
            $weeks[$weekNumber]++;
        }

        $series = [];
        foreach ($weeks as $weekNumber => $count) {
            $series[] = ['label' => "W{$weekNumber}", 'count' => $count];
        }

        return $series;
    }

    private function monthlyCountSeries(string $modelClass, string $dateColumn, array $studentIds): array
    {
        $currentYear = Carbon::now()->year;
        $rows = $modelClass::query()
            ->selectRaw("MONTH({$dateColumn}) as month, COUNT(id) as aggregate")
            ->whereIn('student_id', $studentIds)
            ->where('status', 'present')
            ->whereYear($dateColumn, $currentYear)
            ->groupBy('month')
            ->pluck('aggregate', 'month');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $series = [];
        foreach ($months as $index => $month) {
            $series[] = [
                'label' => $month,
                'count' => (int) ($rows[$index + 1] ?? 0),
            ];
        }

        return $series;
    }
}
