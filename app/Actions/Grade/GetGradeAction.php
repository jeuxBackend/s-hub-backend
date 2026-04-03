<?php

namespace App\Actions\Grade;

use App\Models\StudentGrade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetGradeAction
{
    public function handle(array $filters): Collection|LengthAwarePaginator
    {
        $query = StudentGrade::with(['student', 'subject', 'recordedBy', 'classroom']);

        // Filter by date
        if (!empty($filters['date'])) {
            try {
                $date = Carbon::parse($filters['date'])->toDateString();
                $query->whereDate('date', $date);
            } catch (\Exception $e) {
                // Handle invalid date format
                throw new \InvalidArgumentException('Invalid date format provided.');
            }
        }

        // Filter by student
        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        // Filter by classroom
        if (!empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        // Filter by subject
        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        // Filter by term
        if (!empty($filters['term'])) {
            $query->where('term', $filters['term']);
        }

        // Default sorting
        $query->orderByDesc('date');

        // Pagination
        if (!empty($filters['paginate']) && $filters['paginate']) {
            $perPage = $filters['per_page'] ?? 10;
            return $query->paginate($perPage);
        }

        return $query->get();
    }
}
