<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\StudentPerformance;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Enums\AttendanceStatus;
use App\Http\Resources\ClassroomResource;
use Throwable;

class TeacherClassroomController extends Controller
{
    /**
     * Get all classrooms where the teacher is teaching a subject or is in charge.
     */
    public function index()
    {
        try {
            $teacher = auth()->user();
            $teacherId = $teacher->id;
            $institutionId = $teacher->institution_id;

            $classrooms = Classroom::query()
                ->where('institution_id', $institutionId)
                ->where(function ($query) use ($teacherId) {
                    $query->where('in_charge_id', $teacherId)
                        ->orWhereHas('subjects', function ($q) use ($teacherId) {
                            $q->where('teacher_id', $teacherId);
                        });
                })
                ->with(['inCharge', 'subjects.teacher', 'teachers', 'students'])
                ->get();

            $classrooms->transform(function ($classroom) {
                // Total Students
                $classroom->students_count = $classroom->students()->count();

                // Average Performance
                $classroom->average_performance = StudentPerformance::where('class_id', $classroom->id)
                    ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                    ->value('avg_perf');
                $classroom->average_performance = round($classroom->average_performance, 2);

                // Average Attendance 
                $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                    ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                    ->first();
                $classroom->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

                // Tuition Counts
                $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('status', 'paid')
                    ->count();

                $classroom->owing_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('due_amount', '>', 0)
                    ->count();

                return $classroom;
            });

            return $this->successResponse(
                ClassroomResource::collection($classrooms),
                'Teacher classrooms retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get details of a specific classroom.
     */
    public function show($id)
    {
        try {
            $teacher = auth()->user();
            $teacherId = $teacher->id;
            $institutionId = $teacher->institution_id;

            $classroom = Classroom::query()
                ->where('id', $id)
                ->where('institution_id', $institutionId)
                ->where(function ($query) use ($teacherId) {
                    $query->where('in_charge_id', $teacherId)
                        ->orWhereHas('subjects', function ($q) use ($teacherId) {
                            $q->where('teacher_id', $teacherId);
                        });
                })
                ->with(['inCharge', 'subjects.teacher', 'teachers', 'students.todayAttendance'])
                ->first();

            if (!$classroom) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            // Total Students
            $classroom->students_count = $classroom->students()->count();

            // Average Performance
            $classroom->average_performance = StudentPerformance::where('class_id', $classroom->id)
                ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                ->value('avg_perf');
            $classroom->average_performance = round($classroom->average_performance, 2);

            // Average Attendance 
            $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                ->first();
            $classroom->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

            // Tuition Counts
            $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                ->where('status', 'paid')
                ->count();

            $classroom->owing_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                ->where('due_amount', '>', 0)
                ->count();

            return $this->successResponse(
                new ClassroomResource($classroom),
                'Classroom details retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
