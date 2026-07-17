<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\BulkUpsertClassSubjectRequirementRequest;
use App\Http\Requests\Timetable\BulkUpsertTeacherAvailabilityRequest;
use App\Http\Requests\Timetable\UpsertSchoolTimetableConfigRequest;
use App\Models\ClassSubjectRequirement;
use App\Models\Classroom;
use App\Models\SchoolTimetableConfig;
use App\Models\Subject;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Support\TimetableEntryResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class TimetableConfigController extends Controller
{
    public function showCurrent(TimetableEntryResolver $resolver)
    {
        try {
            $institutionId = auth()->user()->institution_id;

            $config = SchoolTimetableConfig::query()
                ->where('institution_id', $institutionId)
                ->with(['workingDays', 'breakPeriods'])
                ->where('is_active', true)
                ->latest()
                ->first();

            if (!$config) {
                return $this->successResponse(null, 'No active timetable configuration found.');
            }

            return $this->successResponse($this->transformConfig($config, $resolver), 'Timetable configuration retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function upsertConfig(UpsertSchoolTimetableConfigRequest $request, TimetableEntryResolver $resolver)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();

            $config = DB::transaction(function () use ($institutionId, $data) {
                $config = isset($data['config_id'])
                    ? SchoolTimetableConfig::query()->where('institution_id', $institutionId)->findOrFail($data['config_id'])
                    : new SchoolTimetableConfig(['institution_id' => $institutionId]);

                if (($data['is_active'] ?? true) === true) {
                    SchoolTimetableConfig::query()
                        ->where('institution_id', $institutionId)
                        ->where('id', '!=', $config->id)
                        ->update(['is_active' => false]);
                }

                $config->fill([
                    'academic_year' => $data['academic_year'] ?? null,
                    'term' => $data['term'] ?? null,
                    'mode' => $data['mode'],
                    'school_start_time' => $data['school_start_time'],
                    'school_end_time' => $data['school_end_time'],
                    'lesson_duration_minutes' => $data['lesson_duration_minutes'],
                    'is_active' => $data['is_active'] ?? true,
                    'notes' => $data['notes'] ?? null,
                ]);
                $config->save();

                $config->workingDays()->delete();
                $config->workingDays()->createMany(collect($data['working_days'])->map(function (array $day) {
                    return [
                        'weekday' => $day['weekday'],
                        'is_open' => $day['is_open'] ?? true,
                    ];
                })->all());

                $config->breakPeriods()->delete();
                $config->breakPeriods()->createMany(collect($data['break_periods'] ?? [])->map(function (array $period) {
                    return [
                        'weekday' => $period['weekday'] ?? null,
                        'name' => $period['name'],
                        'break_type' => $period['break_type'] ?? 'break',
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                    ];
                })->all());

                return $config->load(['workingDays', 'breakPeriods']);
            });

            return $this->successResponse($this->transformConfig($config, $resolver), 'Timetable configuration saved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function indexTeacherAvailabilities(Request $request)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'class_name' => ['sometimes', 'string', 'max:255'],
                'classname' => ['sometimes', 'string', 'max:255'],
            ]);
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $availabilities = TeacherAvailability::query()
                ->where('institution_id', $institutionId)
                ->where('config_id', $activeConfig->id)
                ->with('teacher:id,first_name,sur_name')
                ->orderBy('teacher_id')
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->paginate($validated['per_page'] ?? 15);

            $items = $availabilities->getCollection()->map(function (TeacherAvailability $availability) {
                return [
                    'id' => $availability->id,
                    'teacher_id' => $availability->teacher_id,
                    'teacher_name' => $availability->teacher?->full_name,
                    'config_id' => $availability->config_id,
                    'weekday' => (int) $availability->weekday,
                    'start_time' => $availability->start_time,
                    'end_time' => $availability->end_time,
                    'availability_type' => $availability->availability_type,
                ];
            });

            return $this->successResponse([
                'config_id' => $activeConfig->id,
                'items' => $items,
                'pagination' => [
                    'total' => $availabilities->total(),
                    'per_page' => $availabilities->perPage(),
                    'current_page' => $availabilities->currentPage(),
                    'last_page' => $availabilities->lastPage(),
                    'from' => $availabilities->firstItem(),
                    'to' => $availabilities->lastItem(),
                ],
            ], 'Teacher availabilities retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function bulkUpsertTeacherAvailabilities(BulkUpsertTeacherAvailabilityRequest $request)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $teacherIds = collect($data['teacher_availabilities'])->pluck('teacher_id')->unique()->values();

            $validTeacherIds = User::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $teacherIds)
                ->whereIn('role', ['teacher', 'school-admin'])
                ->pluck('id');

            if ($validTeacherIds->count() !== $teacherIds->count()) {
                return $this->errorResponse('One or more teachers do not belong to your institution.', 422);
            }

            DB::transaction(function () use ($institutionId, $data, $activeConfig) {
                foreach ($data['teacher_availabilities'] as $item) {
                    TeacherAvailability::updateOrCreate(
                        [
                            'institution_id' => $institutionId,
                            'teacher_id' => $item['teacher_id'],
                            'config_id' => $activeConfig->id,
                            'weekday' => $item['weekday'],
                            'start_time' => $item['start_time'],
                            'end_time' => $item['end_time'],
                        ],
                        [
                            'availability_type' => $item['availability_type'],
                        ]
                    );
                }
            });

            $availabilities = TeacherAvailability::query()
                ->where('institution_id', $institutionId)
                ->where('config_id', $activeConfig->id)
                ->with('teacher:id,first_name,sur_name')
                ->orderBy('teacher_id')
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get()
                ->map(function (TeacherAvailability $availability) {
                    return [
                        'id' => $availability->id,
                        'teacher_id' => $availability->teacher_id,
                        'teacher_name' => $availability->teacher?->full_name,
                        'config_id' => $availability->config_id,
                        'weekday' => (int) $availability->weekday,
                        'start_time' => $availability->start_time,
                        'end_time' => $availability->end_time,
                        'availability_type' => $availability->availability_type,
                    ];
                });

            return $this->successResponse([
                'config_id' => $activeConfig->id,
                'items' => $availabilities,
            ], 'Teacher availabilities saved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updateTeacherAvailability(Request $request, int $id)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $data = $request->validate([
                'availabilities' => ['required', 'array'],
                'availabilities.*.id' => ['sometimes', 'integer', 'exists:teacher_availabilities,id'],
                'availabilities.*.weekday' => ['required', 'integer', 'between:1,7'],
                'availabilities.*.start_time' => ['required', 'date_format:H:i'],
                'availabilities.*.end_time' => ['required', 'date_format:H:i'],
                'availabilities.*.availability_type' => ['required', 'string', 'in:available,unavailable,preferred'],
            ]);

            $teacher = User::query()
                ->where('institution_id', $institutionId)
                ->where('id', $id)
                ->whereIn('role', ['teacher', 'school-admin'])
                ->first();

            if (!$teacher) {
                return $this->errorResponse('Teacher does not belong to your institution.', 422);
            }

            $seenSlots = [];

            foreach ($data['availabilities'] as $availability) {
                if ($availability['end_time'] <= $availability['start_time']) {
                    return $this->errorResponse('End time must be after start time.', 422);
                }

                $slotKey = implode('|', [
                    $availability['weekday'],
                    $availability['start_time'],
                    $availability['end_time'],
                ]);

                if (isset($seenSlots[$slotKey])) {
                    return $this->errorResponse('Duplicate availability slots are not allowed for the same teacher.', 422);
                }

                $seenSlots[$slotKey] = true;
            }

            $submittedIds = collect($data['availabilities'])->pluck('id')->filter()->values();

            if ($submittedIds->unique()->count() !== $submittedIds->count()) {
                return $this->errorResponse('Duplicate availability record ids are not allowed.', 422);
            }

            if ($submittedIds->isNotEmpty()) {
                $validSubmittedIds = TeacherAvailability::query()
                    ->where('institution_id', $institutionId)
                    ->where('config_id', $activeConfig->id)
                    ->where('teacher_id', $teacher->id)
                    ->whereIn('id', $submittedIds)
                    ->count();

                if ($validSubmittedIds !== $submittedIds->count()) {
                    return $this->errorResponse('One or more availability records do not belong to this teacher.', 422);
                }
            }

            DB::transaction(function () use ($data, $institutionId, $activeConfig, $teacher) {
                $keptIds = [];

                foreach ($data['availabilities'] as $item) {
                    if (!empty($item['id'])) {
                        $availability = TeacherAvailability::query()
                            ->where('institution_id', $institutionId)
                            ->where('config_id', $activeConfig->id)
                            ->where('teacher_id', $teacher->id)
                            ->findOrFail($item['id']);

                        $availability->update([
                            'weekday' => $item['weekday'],
                            'start_time' => $item['start_time'],
                            'end_time' => $item['end_time'],
                            'availability_type' => $item['availability_type'],
                        ]);
                    } else {
                        $availability = TeacherAvailability::updateOrCreate(
                            [
                                'institution_id' => $institutionId,
                                'teacher_id' => $teacher->id,
                                'config_id' => $activeConfig->id,
                                'weekday' => $item['weekday'],
                                'start_time' => $item['start_time'],
                                'end_time' => $item['end_time'],
                            ],
                            [
                                'availability_type' => $item['availability_type'],
                            ]
                        );
                    }

                    $keptIds[] = $availability->id;
                }

                TeacherAvailability::query()
                    ->where('institution_id', $institutionId)
                    ->where('config_id', $activeConfig->id)
                    ->where('teacher_id', $teacher->id)
                    ->when(!empty($keptIds), fn ($query) => $query->whereNotIn('id', $keptIds))
                    ->delete();

            });

            $availabilities = TeacherAvailability::query()
                ->where('institution_id', $institutionId)
                ->where('config_id', $activeConfig->id)
                ->where('teacher_id', $teacher->id)
                ->with('teacher:id,first_name,sur_name')
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get()
                ->map(fn (TeacherAvailability $availability) => $this->transformTeacherAvailability($availability));

            return $this->successResponse([
                'config_id' => $activeConfig->id,
                'teacher_id' => $teacher->id,
                'items' => $availabilities,
            ], 'Teacher availabilities synced successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroyTeacherAvailability(int $id)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $availability = TeacherAvailability::query()
                ->where('institution_id', $institutionId)
                ->where('config_id', $activeConfig->id)
                ->findOrFail($id);

            $availability->delete();

            return $this->successResponse(null, 'Teacher availability deleted successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function indexClassSubjectRequirements(Request $request)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'class_name' => ['sometimes', 'string', 'max:255'],
                'classname' => ['sometimes', 'string', 'max:255'],
            ]);
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $requirements = ClassSubjectRequirement::query()
                ->where('institution_id', $institutionId)
                ->when($validated['class_name'] ?? $validated['classname'] ?? null, function ($query, string $className) {
                    $query->whereHas('classroom', function ($classroomQuery) use ($className) {
                        $classroomQuery->where('name', 'like', '%' . $className . '%');
                    });
                })
                ->with([
                    'classroom:id,name,code',
                    'subject:id,name,classroom_id',
                    'teacher:id,first_name,sur_name',
                ])
                ->orderBy('classroom_id')
                ->orderBy('subject_id')
                ->paginate($validated['per_page'] ?? 15);

            $items = $requirements->getCollection()
                ->map(function (ClassSubjectRequirement $requirement) {
                    return [
                        'id' => $requirement->id,
                        'classroom_id' => $requirement->classroom_id,
                        'classroom_name' => $requirement->classroom?->name,
                        'subject_id' => $requirement->subject_id,
                        'subject_name' => $requirement->subject?->name,
                        'teacher_id' => $requirement->teacher_id,
                        'teacher_name' => $requirement->teacher?->full_name,
                        'lessons_per_week' => (int) $requirement->lessons_per_week,
                        'double_period_allowed' => (bool) $requirement->double_period_allowed,
                        'priority' => (int) $requirement->priority,
                        'is_active' => (bool) $requirement->is_active,
                    ];
                });

            return $this->successResponse([
                'config_id' => $activeConfig?->id,
                'items' => $items,
                'pagination' => [
                    'total' => $requirements->total(),
                    'per_page' => $requirements->perPage(),
                    'current_page' => $requirements->currentPage(),
                    'last_page' => $requirements->lastPage(),
                    'from' => $requirements->firstItem(),
                    'to' => $requirements->lastItem(),
                ],
            ], 'Class subject requirements retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function bulkUpsertClassSubjectRequirements(BulkUpsertClassSubjectRequirementRequest $request)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();

            $classroomIds = collect($data['requirements'])->pluck('classroom_id')->unique()->values();
            $subjectIds = collect($data['requirements'])->pluck('subject_id')->unique()->values();
            $teacherIds = collect($data['requirements'])->pluck('teacher_id')->filter()->unique()->values();
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $classroomInChargeIds = Classroom::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $classroomIds)
                ->pluck('in_charge_id', 'id');

            if ($activeConfig?->mode === 'primary') {
                $teacherIds = collect($data['requirements'])
                    ->map(fn (array $item) => $item['teacher_id'] ?? $classroomInChargeIds[$item['classroom_id']] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
            }

            if (Classroom::query()->where('institution_id', $institutionId)->whereIn('id', $classroomIds)->count() !== $classroomIds->count()) {
                return $this->errorResponse('One or more classrooms do not belong to your institution.', 422);
            }

            if (Subject::query()->where('institution_id', $institutionId)->whereIn('id', $subjectIds)->count() !== $subjectIds->count()) {
                return $this->errorResponse('One or more subjects do not belong to your institution.', 422);
            }

            if ($teacherIds->isNotEmpty() && User::query()->where('institution_id', $institutionId)->whereIn('id', $teacherIds)->count() !== $teacherIds->count()) {
                return $this->errorResponse('One or more teachers do not belong to your institution.', 422);
            }

            DB::transaction(function () use ($institutionId, $data, $activeConfig, $classroomInChargeIds) {
                foreach ($data['requirements'] as $item) {
                    $teacherId = $item['teacher_id'] ?? null;

                    if (!$teacherId && $activeConfig?->mode === 'primary') {
                        $teacherId = $classroomInChargeIds[$item['classroom_id']] ?? null;
                    }

                    ClassSubjectRequirement::updateOrCreate(
                        [
                            'institution_id' => $institutionId,
                            'classroom_id' => $item['classroom_id'],
                            'subject_id' => $item['subject_id'],
                        ],
                        [
                            'teacher_id' => $teacherId,
                            'lessons_per_week' => $item['lessons_per_week'],
                            'double_period_allowed' => $item['double_period_allowed'] ?? false,
                            'priority' => $item['priority'] ?? 1,
                            'is_active' => $item['is_active'] ?? true,
                        ]
                    );
                }
            });

            $requirements = ClassSubjectRequirement::query()
                ->where('institution_id', $institutionId)
                ->with([
                    'classroom:id,name,code',
                    'subject:id,name,classroom_id',
                    'teacher:id,first_name,sur_name',
                ])
                ->orderBy('classroom_id')
                ->orderBy('subject_id')
                ->get()
                ->map(function (ClassSubjectRequirement $requirement) {
                    return [
                        'id' => $requirement->id,
                        'classroom_id' => $requirement->classroom_id,
                        'classroom_name' => $requirement->classroom?->name,
                        'subject_id' => $requirement->subject_id,
                        'subject_name' => $requirement->subject?->name,
                        'teacher_id' => $requirement->teacher_id,
                        'teacher_name' => $requirement->teacher?->full_name,
                        'lessons_per_week' => (int) $requirement->lessons_per_week,
                        'double_period_allowed' => (bool) $requirement->double_period_allowed,
                        'priority' => (int) $requirement->priority,
                        'is_active' => (bool) $requirement->is_active,
                    ];
                });

            return $this->successResponse([
                'config_id' => $activeConfig?->id,
                'items' => $requirements,
            ], 'Class subject requirements saved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updateClassSubjectRequirement(Request $request, int $id)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $data = $request->validate([
                'classroom_id' => ['sometimes', 'integer', 'exists:classrooms,id'],
                'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
                'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
                'lessons_per_week' => ['sometimes', 'integer', 'min:1'],
                'double_period_allowed' => ['sometimes', 'boolean'],
                'priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
                'is_active' => ['sometimes', 'boolean'],
            ]);

            $requirement = ClassSubjectRequirement::query()
                ->where('institution_id', $institutionId)
                ->findOrFail($id);

            $classroomId = $data['classroom_id'] ?? $requirement->classroom_id;
            $subjectId = $data['subject_id'] ?? $requirement->subject_id;
            $teacherId = array_key_exists('teacher_id', $data)
                ? $data['teacher_id']
                : $requirement->teacher_id;

            $classroom = Classroom::query()
                ->where('institution_id', $institutionId)
                ->find($classroomId);

            if (!$classroom) {
                return $this->errorResponse('Classroom does not belong to your institution.', 422);
            }

            $subject = Subject::query()
                ->where('institution_id', $institutionId)
                ->where('classroom_id', $classroomId)
                ->find($subjectId);

            if (!$subject) {
                return $this->errorResponse('Subject does not belong to the selected classroom.', 422);
            }

            if (!$teacherId && $activeConfig->mode === 'primary') {
                $teacherId = $classroom->in_charge_id;
            }

            if ($teacherId) {
                $teacherExists = User::query()
                    ->where('institution_id', $institutionId)
                    ->where('id', $teacherId)
                    ->whereIn('role', ['teacher', 'school-admin'])
                    ->exists();

                if (!$teacherExists) {
                    return $this->errorResponse('Teacher does not belong to your institution.', 422);
                }
            }

            $duplicateExists = ClassSubjectRequirement::query()
                ->where('institution_id', $institutionId)
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $subjectId)
                ->where('id', '!=', $requirement->id)
                ->exists();

            if ($duplicateExists) {
                return $this->errorResponse('A requirement already exists for this classroom and subject.', 422);
            }

            $requirement->update([
                'classroom_id' => $classroomId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'lessons_per_week' => $data['lessons_per_week'] ?? $requirement->lessons_per_week,
                'double_period_allowed' => $data['double_period_allowed'] ?? $requirement->double_period_allowed,
                'priority' => $data['priority'] ?? $requirement->priority,
                'is_active' => $data['is_active'] ?? $requirement->is_active,
            ]);

            $requirement->load([
                'classroom:id,name,code',
                'subject:id,name,classroom_id',
                'teacher:id,first_name,sur_name',
            ]);

            return $this->successResponse([
                'config_id' => $activeConfig->id,
                'item' => $this->transformClassSubjectRequirement($requirement),
            ], 'Class subject requirement updated successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroyClassSubjectRequirement(int $id)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $activeConfig = $this->activeTimetableConfig($institutionId);

            if (!$activeConfig) {
                return $this->errorResponse('Please configure the school timetable first.', 422);
            }

            $requirement = ClassSubjectRequirement::query()
                ->where('institution_id', $institutionId)
                ->findOrFail($id);

            $requirement->delete();

            return $this->successResponse(null, 'Class subject requirement deleted successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function transformConfig(SchoolTimetableConfig $config, TimetableEntryResolver $resolver): array
    {
        return [
            'id' => $config->id,
            'institution_id' => $config->institution_id,
            'academic_year' => $config->academic_year,
            'term' => $config->term,
            'mode' => $config->mode,
            'school_start_time' => $config->school_start_time,
            'school_end_time' => $config->school_end_time,
            'lesson_duration_minutes' => (int) $config->lesson_duration_minutes,
            'is_active' => (bool) $config->is_active,
            'notes' => $config->notes,
            'working_days' => $config->workingDays
                ->sortBy('weekday')
                ->map(fn ($day) => [
                    'id' => $day->id,
                    'weekday' => (int) $day->weekday,
                    'weekday_name' => $resolver->weekdayName((int) $day->weekday),
                    'is_open' => (bool) $day->is_open,
                ])
                ->values()
                ->all(),
            'break_periods' => $config->breakPeriods
                ->sortBy([
                    ['weekday', 'asc'],
                    ['start_time', 'asc'],
                ])
                ->map(fn ($period) => [
                    'id' => $period->id,
                    'weekday' => $period->weekday ? (int) $period->weekday : null,
                    'weekday_name' => $period->weekday ? $resolver->weekdayName((int) $period->weekday) : 'All Days',
                    'name' => $period->name,
                    'break_type' => $period->break_type,
                    'start_time' => $period->start_time,
                    'end_time' => $period->end_time,
                ])
                ->values()
                ->all(),
        ];
    }

    private function activeTimetableConfig(int $institutionId): ?SchoolTimetableConfig
    {
        return SchoolTimetableConfig::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    private function transformTeacherAvailability(TeacherAvailability $availability): array
    {
        return [
            'id' => $availability->id,
            'teacher_id' => $availability->teacher_id,
            'teacher_name' => $availability->teacher?->full_name,
            'config_id' => $availability->config_id,
            'weekday' => (int) $availability->weekday,
            'start_time' => $availability->start_time,
            'end_time' => $availability->end_time,
            'availability_type' => $availability->availability_type,
        ];
    }

    private function transformClassSubjectRequirement(ClassSubjectRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'classroom_id' => $requirement->classroom_id,
            'classroom_name' => $requirement->classroom?->name,
            'subject_id' => $requirement->subject_id,
            'subject_name' => $requirement->subject?->name,
            'teacher_id' => $requirement->teacher_id,
            'teacher_name' => $requirement->teacher?->full_name,
            'lessons_per_week' => (int) $requirement->lessons_per_week,
            'double_period_allowed' => (bool) $requirement->double_period_allowed,
            'priority' => (int) $requirement->priority,
            'is_active' => (bool) $requirement->is_active,
        ];
    }
}
