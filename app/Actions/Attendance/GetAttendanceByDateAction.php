<?php

namespace App\Actions\Attendance;

use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAttendanceByDateAction
{
    public function handle(array $filters): Collection|LengthAwarePaginator
    {
        $date = isset($filters['date'])
            ? Carbon::parse($filters['date'])->toDateString()
            : now()->toDateString();

        $query = StudentAttendance::with('student')
            ->whereDate('date', $date);

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['classroom_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('classroom_id', $filters['classroom_id']);
            });
        }

        if (!empty($filters['paginate']) && $filters['paginate']) {
            return $query->paginate(10);
        }

        return $query->get();
    }
}
