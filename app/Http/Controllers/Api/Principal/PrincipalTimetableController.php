<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\TimetableQueryRequest;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SchoolTimetableConfig;
use App\Http\Resources\TimetableEntryResource;
use App\Support\TimetableCsvExporter;
use App\Support\TimetableEntryResolver;
use App\Support\TimetableViewFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

class PrincipalTimetableController extends Controller
{
    /**
     * Get grouped timetable for the whole institution.
     */
    public function getSchoolTimetable(TimetableQueryRequest $request, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, TimetableCsvExporter $csvExporter)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $filters = $request->validated();
            $config = $this->resolveDisplayConfig($filters, $institutionId);

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->with([
                    'subject',
                    'teacher',
                    'classroom' => function ($query) {
                        $query->select('id', 'name', 'code');
                    },
                ]);

            $this->applyCommonFilters($entries, $filters, $config);

            $entries = $entries
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('start_time')
                ->orderBy('classroom_id')
                ->get();

            if (($filters['export'] ?? null) === 'csv') {
                return $csvExporter->download($entries, $resolver, 'school-timetable.csv');
            }

            $resource = TimetableEntryResource::collection($entries);
            $resolved = $resource->resolve();

            return $this->successResponse(
                ($filters['grouped'] ?? true) ? $formatter->groupWeekly($resolved, $resolver, $config) : $resolved,
                'School timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get timetable for a specific teacher.
     */
    public function getTeacherTimetable(TimetableQueryRequest $request, $teacherId, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, TimetableCsvExporter $csvExporter)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $filters = $request->validated();
            $config = $this->resolveDisplayConfig($filters, $institutionId);

            // Verify teacher belongs to the same institution
            $teacher = User::where('id', $teacherId)
                ->where('institution_id', $institutionId)
                ->whereIn('role', [\App\Enums\UserRole::Teacher->value, \App\Enums\UserRole::SchoolAdmin->value])
                ->first();

            if (!$teacher) {
                return $this->errorResponse('Teacher not found in your institution.', 404);
            }

            $entries = TimetableEntry::query()
                ->where('teacher_id', $teacher->id)
                ->with([
                    'subject',
                    'teacher',
                    'classroom' => function ($q) {
                        $q->select('id', 'name', 'code')->withCount('students');
                    },
                ]);

            $this->applyCommonFilters($entries, $filters, $config);

            $entries = $entries
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('start_time')
                ->get();

            if (($filters['export'] ?? null) === 'csv') {
                return $csvExporter->download($entries, $resolver, "teacher-{$teacher->id}-timetable.csv");
            }

            return $this->successResponse(
                $this->formatTeacherView(TimetableEntryResource::collection($entries)->resolve(), $filters, $resolver, $formatter),
                'Teacher timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get timetable for a specific classroom.
     */
    public function getClassroomTimetable(TimetableQueryRequest $request, $classroomId, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, TimetableCsvExporter $csvExporter)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $filters = $request->validated();
            $config = $this->resolveDisplayConfig($filters, $institutionId);

            // Verify classroom belongs to the same institution
            $classroom = Classroom::where('id', $classroomId)
                ->where('institution_id', $institutionId)
                ->first();

            if (!$classroom) {
                return $this->errorResponse('Classroom not found in your institution.', 404);
            }

            $entries = TimetableEntry::query()
                ->where('classroom_id', $classroom->id)
                ->with(['subject', 'teacher', 'classroom']);

            $this->applyCommonFilters($entries, $filters, $config);

            $entries = $entries
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('start_time')
                ->get();

            if (($filters['export'] ?? null) === 'csv') {
                return $csvExporter->download($entries, $resolver, "classroom-{$classroom->id}-timetable.csv");
            }

            return $this->successResponse(
                ($filters['grouped'] ?? true)
                    ? $formatter->groupWeekly(TimetableEntryResource::collection($entries)->resolve(), $resolver)
                    : TimetableEntryResource::collection($entries),
                'Classroom timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getSubjectTimetable(TimetableQueryRequest $request, $subjectId, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, TimetableCsvExporter $csvExporter)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $filters = $request->validated();
            $config = $this->resolveDisplayConfig($filters, $institutionId);

            $subject = Subject::query()
                ->where('institution_id', $institutionId)
                ->find($subjectId);

            if (!$subject) {
                return $this->errorResponse('Subject not found in your institution.', 404);
            }

            $entries = TimetableEntry::query()
                ->where('subject_id', $subject->id)
                ->with(['subject', 'teacher', 'classroom']);

            $this->applyCommonFilters($entries, $filters, $config);

            $entries = $entries
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('start_time')
                ->get();

            if (($filters['export'] ?? null) === 'csv') {
                return $csvExporter->download($entries, $resolver, "subject-{$subject->id}-timetable.csv");
            }

            return $this->successResponse(
                ($filters['grouped'] ?? true)
                    ? $formatter->groupWeekly(TimetableEntryResource::collection($entries)->resolve(), $resolver)
                    : TimetableEntryResource::collection($entries),
                'Subject timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function applyCommonFilters(Builder $query, array $filters, ?SchoolTimetableConfig $config = null): void
    {
        $query
            ->when($config, fn ($q) => $q->where('config_id', $config->id))
            ->when(isset($filters['academic_year']), fn ($q) => $q->where('academic_year', $filters['academic_year']))
            ->when(isset($filters['term']), fn ($q) => $q->where('term', $filters['term']))
            ->when(isset($filters['teacher_id']), fn ($q) => $q->where('teacher_id', $filters['teacher_id']))
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
                        ->orWhereHas('classroom', fn ($classroomQuery) => $classroomQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('teacher', function ($teacherQuery) use ($search) {
                            $teacherQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('sur_name', 'like', "%{$search}%");
                        });
                });
            });
    }

    private function formatTeacherView(array $entries, array $filters, TimetableEntryResolver $resolver, TimetableViewFormatter $formatter, ?SchoolTimetableConfig $config = null): array
    {
        $view = $filters['view'] ?? 'weekly';

        return match ($view) {
            'daily' => $formatter->buildDaily(
                $entries,
                isset($filters['date']) ? Carbon::parse($filters['date']) : now(),
                $resolver,
                $config
            ),
            'monthly' => $formatter->buildMonthly(
                $entries,
                now()->setYear($filters['year'] ?? now()->year)->setMonth($filters['month'] ?? now()->month)->startOfMonth(),
                $resolver,
                $config
            ),
            'yearly' => $formatter->buildYearly($entries, (int) ($filters['year'] ?? now()->year), $resolver, $config),
            default => [
                'view' => 'weekly',
                'grouped_entries' => $formatter->groupWeekly($entries, $resolver, $config),
            ],
        };
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

    private function groupEntries(array $entries, TimetableEntryResolver $resolver): array
    {
        return collect($entries)
            ->groupBy('weekday')
            ->map(function ($dayEntries, $weekday) use ($resolver) {
                return [
                    'weekday' => (int) $weekday,
                    'weekday_name' => $resolver->weekdayName((int) $weekday),
                    'entries' => array_values($dayEntries->all()),
                ];
            })
            ->values()
            ->all();
    }
}
