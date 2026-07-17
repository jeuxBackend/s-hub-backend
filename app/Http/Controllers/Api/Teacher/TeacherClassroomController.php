<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\StudentPerformance;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Models\SchoolTimetableConfig;
use App\Enums\AttendanceStatus;
use App\Http\Resources\ClassroomResource;
use App\Http\Requests\Timetable\TimetableQueryRequest;
use App\Support\TimetableCsvExporter;
use App\Support\TimetableEntryResolver;
use App\Support\TimetableViewFormatter;
use Illuminate\Support\Carbon;
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
                        ->orWhereHas('classSubjectRequirements', fn ($q) => $q->where('teacher_id', $teacherId))
                        ->orWhereHas('timetableEntries', fn ($q) => $q->where('teacher_id', $teacherId))
                        ->orWhereHas('teachers', function ($q) use ($teacherId) {
                            $q->where('teacher_id', $teacherId);
                        });
                })
                ->with(['inCharge', 'subjects.classSubjectRequirement.teacher', 'classSubjectRequirements.teacher', 'teachers', 'students.guardian.authorizedPickup'])
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
                        ->orWhereHas('classSubjectRequirements', fn ($q) => $q->where('teacher_id', $teacherId))
                        ->orWhereHas('timetableEntries', fn ($q) => $q->where('teacher_id', $teacherId))
                        ->orWhereHas('teachers', function ($q) use ($teacherId) {
                            $q->where('teacher_id', $teacherId);
                        });
                })
                ->with(['inCharge', 'subjects.classSubjectRequirement.teacher', 'classSubjectRequirements.teacher', 'teachers', 'students.guardian.authorizedPickup', 'students.todayAttendance'])
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

    /**
     * Get timetable for the teacher.
     */
    public function timetable(TimetableQueryRequest $request, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, TimetableCsvExporter $csvExporter)
    {
        try {
            $teacherId = auth()->id();
            $institutionId = auth()->user()->institution_id;
            $filters = $request->validated();
            $config = $this->resolveDisplayConfig($filters, $institutionId);

            $entries = \App\Models\TimetableEntry::query()
                ->where('teacher_id', $teacherId)
                ->with([
                    'subject',
                    'teacher',
                    'classroom' => function ($q) {
                        $q->select('id', 'name', 'code')->withCount('students');
                    },
                ])
                ->when($config, fn ($q) => $q->where('config_id', $config->id))
                ->when(isset($filters['academic_year']), fn ($q) => $q->where('academic_year', $filters['academic_year']))
                ->when(isset($filters['term']), fn ($q) => $q->where('term', $filters['term']))
                ->when(isset($filters['classroom_id']), fn ($q) => $q->where('classroom_id', $filters['classroom_id']))
                ->when(isset($filters['subject_id']), fn ($q) => $q->where('subject_id', $filters['subject_id']))
                ->when(isset($filters['weekday']), fn ($q) => $q->where('weekday', $filters['weekday']))
                ->when(isset($filters['period_number']), fn ($q) => $q->where('period_number', $filters['period_number']))
                ->when(isset($filters['entry_type']), fn ($q) => $q->where('entry_type', $filters['entry_type']))
                ->when(isset($filters['is_locked']), fn ($q) => $q->where('is_locked', $filters['is_locked']))
                ->when(isset($filters['search']), function ($q) use ($filters) {
                    $search = $filters['search'];

                    $q->where(function ($query) use ($search) {
                        $query
                            ->whereHas('subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('classroom', fn ($classroomQuery) => $classroomQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                    });
                })
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('start_time')
                ->get();

            $resolved = \App\Http\Resources\TimetableEntryResource::collection($entries)->resolve();

            if (($filters['export'] ?? null) === 'csv') {
                return $csvExporter->download($entries, $resolver, "teacher-{$teacherId}-timetable.csv");
            }

            $view = $filters['view'] ?? 'weekly';
            $payload = match ($view) {
                'daily' => $formatter->buildDaily($resolved, isset($filters['date']) ? Carbon::parse($filters['date']) : now(), $resolver),
                'monthly' => $formatter->buildMonthly($resolved, now()->setYear($filters['year'] ?? now()->year)->setMonth($filters['month'] ?? now()->month)->startOfMonth(), $resolver),
                'yearly' => $formatter->buildYearly($resolved, (int) ($filters['year'] ?? now()->year), $resolver),
                default => [
                    'view' => 'weekly',
                    'grouped_entries' => $formatter->groupWeekly($resolved, $resolver),
                ],
            };

            return $this->successResponse(
                $payload,
                'Teacher timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function resolveDisplayConfig(array $filters, int $institutionId): ?SchoolTimetableConfig
    {
        return SchoolTimetableConfig::query()
            ->where('institution_id', $institutionId)
            ->with(['workingDays', 'breakPeriods'])
            ->when(
                isset($filters['config_id']),
                fn ($query) => $query->whereKey($filters['config_id']),
                fn ($query) => $query->where('is_active', true)->latest()
            )
            ->first();
    }
}
