<?php

namespace App\Actions\Attendance;

use App\Models\StudentAttendance;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetAttendanceByMonthAction
{
    public function handle(array $filters, bool $paginate = false): LengthAwarePaginator|Collection
    {
        $query = StudentAttendance::query()
            ->with(['student.classroom'])
            ->when(isset($filters['student_id']), fn($q) => $q->where('student_id', $filters['student_id']))
            ->when(isset($filters['classroom_id']), fn($q) => $q->where('classroom_id', $filters['classroom_id']))
            ->when(isset($filters['term']), fn($q) => $q->where('term', $filters['term']))
            
            // 🔍 Exact date filter
            ->when(isset($filters['date']), fn($q) =>
                $q->whereDate('date', $filters['date'])
            )

            // 📅 Month + Year filter
            ->when(isset($filters['month']) && isset($filters['year']), function ($q) use ($filters) {
                $start = Carbon::createFromDate($filters['year'], $filters['month'], 1)->startOfMonth();
                $end = (clone $start)->endOfMonth();
                $q->whereBetween('date', [$start, $end]);
            })

            ->latest('date');

        return $paginate
            ? $query->paginate($filters['per_page'] ?? 10)
            : $query->get();
    }
}