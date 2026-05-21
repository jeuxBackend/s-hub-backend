<?php

namespace App\Actions\Attendance;

use App\Models\Student;
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

        // If classroom_id is provided, we want to return ALL students in that classroom
        // with their attendance status (if marked) or a blank/unmarked state if not.
        if (!empty($filters['classroom_id'])) {
            $studentsQuery = Student::with('guardian')
                ->where('classroom_id', $filters['classroom_id'])
                ->where('status', true);

            // Handle pagination
            if (!empty($filters['paginate']) && $filters['paginate']) {
                $perPage = $filters['per_page'] ?? 10;
                $studentsPaginator = $studentsQuery->paginate($perPage);
                $students = $studentsPaginator->getCollection();
            } else {
                $students = $studentsQuery->get();
            }

            // Fetch marked attendance for these students on this date
            $attendances = StudentAttendance::with(['student.guardian', 'subject', 'recordedBy'])
                ->whereIn('student_id', $students->pluck('id'))
                ->whereDate('date', $date)
                ->get()
                ->keyBy('student_id');

            // Map students to attendance objects (real or transient)
            $items = $students->map(function ($student) use ($attendances, $date) {
                if ($attendances->has($student->id)) {
                    $attendance = $attendances->get($student->id);
                    // Set attendance_status directly on the student model
                    $student->attendance_status = $attendance->status === \App\Enums\AttendanceStatus::Present;
                    return $attendance;
                }

                // Unmarked: set attendance_status to null
                $student->attendance_status = null;

                $transient = new StudentAttendance([
                    'student_id'   => $student->id,
                    'classroom_id' => $student->classroom_id,
                    'date'         => Carbon::parse($date),
                    'status'       => null,
                    'remarks'      => null,
                ]);
                $transient->setRelation('student', $student);
                return $transient;
            });

            if (!empty($filters['paginate']) && $filters['paginate']) {
                return $studentsPaginator->setCollection($items);
            }

            return $items;
        }

        // Default query logic when classroom_id is not specified
        $query = StudentAttendance::with(['student.guardian', 'subject', 'recordedBy'])
            ->whereDate('date', $date);

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['paginate']) && $filters['paginate']) {
            $paginator = $query->paginate($filters['per_page'] ?? 10);
            $paginator->getCollection()->each(function ($attendance) {
                if ($attendance->student) {
                    $attendance->student->attendance_status = $attendance->status === \App\Enums\AttendanceStatus::Present;
                }
            });
            return $paginator;
        }

        $results = $query->get();
        $results->each(function ($attendance) {
            if ($attendance->student) {
                $attendance->student->attendance_status = $attendance->status === \App\Enums\AttendanceStatus::Present;
            }
        });

        return $results;
    }
}
