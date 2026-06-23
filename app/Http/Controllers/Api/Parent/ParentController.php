<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Enums\AttendanceStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class ParentController extends Controller
{
    /**
     * GET /v1/parent/attendances/by-month
     * Fetch monthly attendance and stats for a child.
     */
    public function getAttendanceByMonth(Request $request, \App\Actions\Attendance\GetAttendanceByMonthAction $action)
    {
        $filters = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2000'],
        ]);

        $authId = auth()->id();
        $studentId = $filters['student_id'];

        $student = Student::where('id', $studentId)
            ->where('guardian_id', $authId)
            ->first();

        if (!$student) {
            return $this->errorResponse('Unauthorized access. This student is not registered under your profile.', 403);
        }

        // Determine the target month and year
        $now = Carbon::now();
        $targetMonth = $filters['month'] ?? $now->month;
        $targetYear = $filters['year'] ?? $now->year;

        $targetDate = Carbon::createFromDate($targetYear, $targetMonth, 1)->startOfMonth();

        // Fetch current month's full records using the Action to keep eager loading identical
        $currentAttendances = $action->handle([
            'student_id' => $studentId,
            'month' => $targetMonth,
            'year' => $targetYear,
        ], false);

        // Calculate current month's counters safely grouping by date
        $daysGrouped = $currentAttendances->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
        });

        $totalDays = $daysGrouped->count();
        $presentCount = 0;
        $absentCount = 0;
        $lateCount = 0;
        $excusedCount = 0;

        foreach ($daysGrouped as $date => $records) {
            $present = $records->where('status', AttendanceStatus::Present)->count();
            $late = $records->where('status', AttendanceStatus::Late)->count();
            $absent = $records->where('status', AttendanceStatus::Absent)->count();
            $excused = $records->where('status', AttendanceStatus::Excused)->count();

            if ($present > 0) {
                $presentCount++;
            } elseif ($late > 0) {
                $lateCount++;
            } elseif ($excused > 0 && $absent == 0) {
                $excusedCount++;
            } else {
                $absentCount++;
            }
        }

        // Percentage calculation (Present + Late count as attended)
        $currentPercentage = $totalDays > 0
            ? round((($presentCount + $lateCount) / $totalDays) * 100, 1)
            : 0.0;

        // Fetch last month's stats to calculate difference
        $lastMonthDate = $targetDate->copy()->subMonth();

        $lastAttendances = StudentAttendance::where('student_id', $studentId)
            ->whereBetween('date', [$lastMonthDate->copy()->startOfMonth(), $lastMonthDate->copy()->endOfMonth()])
            ->get();

        $lastDaysGrouped = $lastAttendances->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
        });

        $lastTotalDays = $lastDaysGrouped->count();
        $lastPresentCount = 0;
        $lastLateCount = 0;

        foreach ($lastDaysGrouped as $date => $records) {
            if ($records->where('status', AttendanceStatus::Present)->count() > 0) {
                $lastPresentCount++;
            } elseif ($records->where('status', AttendanceStatus::Late)->count() > 0) {
                $lastLateCount++;
            }
        }

        $lastPercentage = $lastTotalDays > 0
            ? round((($lastPresentCount + $lastLateCount) / $lastTotalDays) * 100, 1)
            : 0.0;

        $difference = $lastTotalDays > 0
            ? round($currentPercentage - $lastPercentage, 1)
            : 0.0;

        return $this->successResponse([
            'student_id' => $student->id,
            'student_name' => trim($student->first_name . ' ' . $student->sur_name),
            'target_month' => $targetDate->format('F Y'),
            'current_month_percentage' => $currentPercentage,
            'last_month_percentage' => $lastPercentage,
            'difference' => $difference,
            'total_days' => $totalDays,
            'present_days' => $presentCount,
            'absent_days' => $absentCount,
            'late_days' => $lateCount,
            'excused_days' => $excusedCount,
            'attendance_log' => \App\Http\Resources\StudentAttendanceResource::collection($currentAttendances),
        ], 'Monthly attendance fetched successfully.');
    }

    /**
     * GET /v1/parent/attendances/by-date
     * Fetch attendance for a child by a specific date.
     */
    public function getAttendanceByDate(Request $request, \App\Actions\Attendance\GetAttendanceByDateAction $action)
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'student_id' => ['required', 'exists:students,id'],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $authId = auth()->id();
        $student = Student::with('classroom')->where('id', $filters['student_id'])
            ->where('guardian_id', $authId)
            ->first();

        if (!$student) {
            return $this->errorResponse('Unauthorized access. This student is not registered under your profile.', 403);
        }

        $isPaginated = $filters['paginate'] ?? false;
        $result = $action->handle($filters);

        if (!$isPaginated) {
            $isClass11Or12 = false;
            if ($student->classroom) {
                $isClass11Or12 = str_contains($student->classroom->name, '11') ||
                    str_contains($student->classroom->name, '12') ||
                    str_contains($student->classroom->code, '11') ||
                    str_contains($student->classroom->code, '12');
            }

            if ($isClass11Or12) {
                return $this->successResponse(
                    \App\Http\Resources\StudentAttendanceResource::collection($result),
                    'Attendance records retrieved successfully.'
                );
            }

            $attendance = $result->first();
            return $attendance
                ? $this->successResponse(new \App\Http\Resources\StudentAttendanceResource($attendance))
                : $this->errorResponse('Attendance record not found.', 404);
        }

        if ($isPaginated && $result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return $this->paginatedResponse(
                \App\Http\Resources\StudentAttendanceResource::collection($result),
                'Attendance records retrieved successfully.'
            );
        }

        return $this->successResponse(
            \App\Http\Resources\StudentAttendanceResource::collection($result),
            'Attendance records retrieved successfully.'
        );
    }

    public function updateAttendanceReason(Request $request, $attendanceId)
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'max:2000'],
            ]);

            $attendance = StudentAttendance::with(['student', 'student.classroom'])
                ->whereKey($attendanceId)
                ->firstOrFail();

            if ($attendance->student?->guardian_id !== auth()->id()) {
                return $this->errorResponse('Unauthorized access to this attendance record.', 403);
            }

            if (($attendance->status?->value ?? $attendance->status) !== AttendanceStatus::Absent->value) {
                return $this->errorResponse('Reason can only be added for absent attendance records.', 422);
            }

            $attendance->update([
                'reason' => $validated['reason'],
            ]);

            return $this->successResponse(
                new \App\Http\Resources\StudentAttendanceResource($attendance->fresh(['student', 'subject', 'recordedBy'])),
                'Attendance reason updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * GET /v1/parent/classrooms
     * Fetch children with their classrooms and individual stats
     */
    public function getChildrenClassrooms()
    {
        try {
            $parentId = auth()->id();

            $children = Student::where('guardian_id', $parentId)
                ->with(['classroom.inCharge', 'classroom.subjects.teacher'])
                ->get()
                ->map(function ($student) {
                    // Child's Average Performance (Percentage)
                    $student->average_performance = \App\Models\StudentPerformance::where('student_id', $student->id)
                        ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                        ->value('avg_perf');

                    // Child's Average Attendance (Percentage)
                    $attendance = StudentAttendance::where('student_id', $student->id)
                        ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                        ->first();
                    $student->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

                    // Tuition Counts for this child
                    $student->paid_tuition = \App\Models\StudentInvoice::where('student_id', $student->id)
                        ->where('status', 'paid')
                        ->count();

                    $student->owing_tuition = \App\Models\StudentInvoice::where('student_id', $student->id)
                        ->where('due_amount', '>', 0)
                        ->count();

                    return [
                        'student_id' => $student->id,
                        'student_name' => trim($student->first_name . ' ' . $student->sur_name),
                        'profile_picture' => $student->profile_picture,
                        'registration_number' => $student->registration_number,
                        'classroom' => $student->classroom ? [
                            'id' => $student->classroom->id,
                            'name' => $student->classroom->name,
                            // 'code' => $student->classroom->code,
                            'in_charge' => $student->classroom->inCharge ? [
                                'id' => $student->classroom->inCharge->id,
                                'name' => $student->classroom->inCharge->full_name,
                            ] : null,
                            'subjects' => $student->classroom->subjects ? \App\Http\Resources\SubjectResource::collection($student->classroom->subjects) : [],
                        ] : null,
                        'average_performance' => round($student->average_performance, 2),
                        'average_attendance' => $student->average_attendance,
                        'paid_tuition' => $student->paid_tuition,
                        'owing_tuition' => $student->owing_tuition,
                    ];
                });

            return $this->successResponse($children, 'Children classrooms and stats retrieved successfully');
        } catch (\Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getGrades(Request $request)
    {
        $filters = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term' => ['required'],
        ]);

        $authId = auth()->id();
        $student = Student::where('id', $filters['student_id'])
            ->where('guardian_id', $authId)
            ->first();

        if (!$student) {
            return $this->errorResponse('Unauthorized access. This student is not registered under your profile.', 403);
        }

        $grades = \App\Models\StudentGrade::where('student_id', $student->id)
            ->where('subject_id', $filters['subject_id'])
            ->where(function($q) use ($filters) {
                $q->where('term', $filters['term'])
                  ->orWhere('type', 'years_marks');
            })
            ->get();

        $result = [
            'student_id' => $student->id,
            'student_name' => trim($student->first_name . ' ' . $student->sur_name),
            'subject_id' => $filters['subject_id'],
            'term' => $filters['term'],
            'grades' => [
                'test_1' => $grades->where('type', 'test_1')->first(),
                'test_2' => $grades->where('type', 'test_2')->first(),
                'test_3' => $grades->where('type', 'test_3')->first(),
                'test_4' => $grades->where('type', 'test_4')->first(),
                'final_marks' => $grades->where('type', 'final_marks')->first(),
                'exam_marks' => $grades->where('type', 'exam_marks')->first(),
                'years_marks' => $grades->where('type', 'years_marks')->first(),
                // Keep these just in case old data exists
                'exam' => $grades->where('type', 'exam')->first(),
                'assignment' => $grades->where('type', 'assignment')->first(),
                'quiz' => $grades->where('type', 'quiz')->first(),
            ]
        ];

        return $this->successResponse($result, 'Grades retrieved successfully.');
    }
}
