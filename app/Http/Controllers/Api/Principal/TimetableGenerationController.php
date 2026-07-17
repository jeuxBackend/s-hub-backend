<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\Rota\GenerateSchoolTimetableAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\BulkLockTimetableEntriesRequest;
use App\Http\Requests\Timetable\BulkMoveTimetableEntriesRequest;
use App\Http\Requests\Timetable\GenerateSchoolTimetableRequest;
use App\Http\Requests\Timetable\StoreTimetableEntryRequest;
use App\Http\Requests\Timetable\SwapTimetableEntriesRequest;
use App\Http\Requests\Timetable\UpdateTimetableEntryRequest;
use App\Http\Resources\TimetableEntryResource;
use App\Models\NotificationLog;
use App\Models\SchoolTimetableConfig;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Support\TimetableEntryResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TimetableGenerationController extends Controller
{
    public function preview(GenerateSchoolTimetableRequest $request, GenerateSchoolTimetableAction $generator, TimetableEntryResolver $resolver)
    {
        try {
            $config = $this->resolveConfig($request->validated(), auth()->user()->institution_id);
            $result = $generator->handle($config);

            $entries = collect($result['all_entries'] ?? $result['entries'])->map(fn(array $entry) => new TimetableEntry($entry));
            $resource = TimetableEntryResource::collection($entries);

            return $this->successResponse([
                'config' => $this->serializeConfig($config),
                'entries' => $resource,
                'grouped_entries' => $this->groupEntries($resource->resolve(), $resolver, $config),
                'generation_summary' => $result['generation_summary'] ?? null,
            ], 'Timetable preview generated successfully.');
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function apply(
        GenerateSchoolTimetableRequest $request,
        GenerateSchoolTimetableAction $generator,
        TimetableEntryResolver $resolver,
        FirebaseNotificationService $firebaseNotificationService
    ) {
        try {
            $payload = $request->validated();
            $config = $this->resolveConfig($payload, auth()->user()->institution_id);
            $result = $generator->handle($config);

            DB::transaction(function () use ($config, $result) {
                TimetableEntry::query()
                    ->where('institution_id', $config->institution_id)
                    ->where('config_id', $config->id)
                    ->where('is_locked', false)
                    ->delete();

                $timestamp = now();
                TimetableEntry::insert(array_map(function (array $entry) use ($timestamp) {
                    $entry = Arr::only($entry, [
                        'institution_id',
                        'config_id',
                        'academic_year',
                        'term',
                        'subject_id',
                        'classroom_id',
                        'teacher_id',
                        'weekday',
                        'period_number',
                        'start_time',
                        'end_time',
                        'entry_type',
                        'version',
                        'is_locked',
                    ]);

                    return [
                        ...$entry,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }, $result['entries']));
            });

            $entries = TimetableEntry::query()
                ->where('institution_id', $config->institution_id)
                ->where('config_id', $config->id)
                ->with(['subject', 'teacher', 'classroom'])
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->orderBy('classroom_id')
                ->get();

            if (($payload['notify_teachers'] ?? true) === true) {
                $this->notifyTeachersAboutUpdatedTimetable($entries, $firebaseNotificationService, $config);
            }

            $resource = TimetableEntryResource::collection($entries);

            return $this->successResponse([
                'config' => $this->serializeConfig($config),
                'entries' => $resource,
                'grouped_entries' => $this->groupEntries($resource->resolve(), $resolver, $config),
                'generation_summary' => $result['generation_summary'] ?? null,
            ], 'Timetable applied successfully.');
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function storeEntry(StoreTimetableEntryRequest $request, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();

            $config = SchoolTimetableConfig::query()
                ->where('institution_id', $institutionId)
                ->with(['workingDays', 'breakPeriods'])
                ->find($data['config_id']);

            if (!$config) {
                return $this->errorResponse('Timetable config not found in your institution.', 404);
            }

            $entry = new TimetableEntry([
                'institution_id' => $institutionId,
                'config_id' => $config->id,
                'academic_year' => $config->academic_year,
                'term' => $config->term,
                'subject_id' => $data['subject_id'],
                'classroom_id' => $data['classroom_id'],
                'teacher_id' => $data['teacher_id'],
                'weekday' => $data['weekday'],
                'period_number' => $data['period_number'],
                'start_time' => $data['start_time'] . ':00',
                'end_time' => $data['end_time'] . ':00',
                'entry_type' => $data['entry_type'] ?? 'lesson',
                'version' => 1,
                'is_locked' => $data['is_locked'] ?? true,
            ]);

            $entry->setRelation('config', $config);
            $this->validateUpdatedEntry($entry);
            $entry->save();
            $entry->load(['subject', 'teacher', 'classroom']);
            $this->notifyTeacherIdsAboutTimetableUpdate(collect([$entry->teacher_id]), $config, $firebaseNotificationService, [
                'change_type' => 'manual_create',
                'entry_ids' => [$entry->id],
            ]);

            return $this->successResponse(new TimetableEntryResource($entry), 'Timetable entry created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function bulkMoveEntries(BulkMoveTimetableEntriesRequest $request, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();
            $entryIds = collect($data['moves'])->pluck('entry_id')->values();

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $entryIds)
                ->with(['config.workingDays', 'config.breakPeriods', 'subject', 'teacher', 'classroom'])
                ->get()
                ->keyBy('id');

            if ($entries->count() !== $entryIds->count()) {
                return $this->errorResponse('One or more timetable entries do not belong to your institution.', 422);
            }

            DB::transaction(function () use ($data, $entries) {
                foreach ($data['moves'] as $move) {
                    $entry = $entries->get($move['entry_id']);
                    $entry->weekday = $move['weekday'];
                    $entry->period_number = $move['period_number'];
                    $entry->start_time = $move['start_time'] . ':00';
                    $entry->end_time = $move['end_time'] . ':00';
                    if (array_key_exists('is_locked', $move)) {
                        $entry->is_locked = (bool) $move['is_locked'];
                    } else {
                        $entry->is_locked = true;
                    }
                }

                foreach ($entries as $entry) {
                    $this->validateUpdatedEntry($entry, $entries->pluck('id')->all());
                }

                foreach ($entries as $entry) {
                    $entry->save();
                }
            });

            $updatedEntries = TimetableEntry::query()
                ->whereIn('id', $entryIds)
                ->with(['subject', 'teacher', 'classroom', 'config'])
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->get();

            $config = $updatedEntries->first()?->config;
            if ($config) {
                $this->notifyTeacherIdsAboutTimetableUpdate($updatedEntries->pluck('teacher_id')->filter()->unique()->values(), $config, $firebaseNotificationService, [
                    'change_type' => 'bulk_move',
                    'entry_ids' => $updatedEntries->pluck('id')->all(),
                ]);
            }

            return $this->successResponse(TimetableEntryResource::collection($updatedEntries), 'Timetable entries moved successfully.');
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function swapEntries(SwapTimetableEntriesRequest $request, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', [$data['entry_id_a'], $data['entry_id_b']])
                ->with(['config.workingDays', 'config.breakPeriods', 'subject', 'teacher', 'classroom'])
                ->get()
                ->keyBy('id');

            if ($entries->count() !== 2) {
                return $this->errorResponse('One or both timetable entries do not belong to your institution.', 422);
            }

            $entryA = $entries->get($data['entry_id_a']);
            $entryB = $entries->get($data['entry_id_b']);

            if ($entryA->config_id !== $entryB->config_id) {
                return $this->errorResponse('Both timetable entries must belong to the same timetable config to swap.', 422);
            }

            $slotA = [
                'weekday' => $entryA->weekday,
                'period_number' => $entryA->period_number,
                'start_time' => $entryA->start_time,
                'end_time' => $entryA->end_time,
            ];
            $slotB = [
                'weekday' => $entryB->weekday,
                'period_number' => $entryB->period_number,
                'start_time' => $entryB->start_time,
                'end_time' => $entryB->end_time,
            ];

            $entryA->fill($slotB);
            $entryB->fill($slotA);
            if (($data['lock_after_swap'] ?? true) === true) {
                $entryA->is_locked = true;
                $entryB->is_locked = true;
            }

            DB::transaction(function () use ($entryA, $entryB) {
                $ignoreIds = [$entryA->id, $entryB->id];
                $this->validateUpdatedEntry($entryA, $ignoreIds);
                $this->validateUpdatedEntry($entryB, $ignoreIds);
                $entryA->save();
                $entryB->save();
            });

            $updatedEntries = TimetableEntry::query()
                ->whereIn('id', [$entryA->id, $entryB->id])
                ->with(['subject', 'teacher', 'classroom', 'config'])
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->get();

            $config = $updatedEntries->first()?->config;
            if ($config) {
                $this->notifyTeacherIdsAboutTimetableUpdate($updatedEntries->pluck('teacher_id')->filter()->unique()->values(), $config, $firebaseNotificationService, [
                    'change_type' => 'swap',
                    'entry_ids' => $updatedEntries->pluck('id')->all(),
                ]);
            }

            return $this->successResponse(TimetableEntryResource::collection($updatedEntries), 'Timetable entries swapped successfully.');
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function bulkLockEntries(BulkLockTimetableEntriesRequest $request, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $data = $request->validated();

            $query = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $data['entry_ids']);

            if ($query->count() !== count($data['entry_ids'])) {
                return $this->errorResponse('One or more timetable entries do not belong to your institution.', 422);
            }

            $entriesBefore = $query->with('config')->get();
            $query->update(['is_locked' => (bool) $data['is_locked']]);

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $data['entry_ids'])
                ->with(['subject', 'teacher', 'classroom'])
                ->orderBy('weekday')
                ->orderBy('period_number')
                ->get();

            $config = $entriesBefore->first()?->config;
            if ($config) {
                $this->notifyTeacherIdsAboutTimetableUpdate($entries->pluck('teacher_id')->filter()->unique()->values(), $config, $firebaseNotificationService, [
                    'change_type' => ((bool) $data['is_locked']) ? 'manual_lock' : 'manual_unlock',
                    'entry_ids' => $entries->pluck('id')->all(),
                ]);
            }

            return $this->successResponse(TimetableEntryResource::collection($entries), 'Timetable entry lock state updated successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroyEntry($entryId, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $entry = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->with('config')
                ->find($entryId);

            if (!$entry) {
                return $this->errorResponse('Timetable entry not found in your institution.', 404);
            }

            $teacherId = $entry->teacher_id;
            $config = $entry->config;
            $deletedEntryId = $entry->id;
            $entry->delete();

            if ($config) {
                $this->notifyTeacherIdsAboutTimetableUpdate(collect([$teacherId])->filter()->unique()->values(), $config, $firebaseNotificationService, [
                    'change_type' => 'manual_delete',
                    'entry_ids' => [$deletedEntryId],
                ]);
            }

            return $this->successResponse(null, 'Timetable entry deleted successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function updateEntry(UpdateTimetableEntryRequest $request, $entryId, FirebaseNotificationService $firebaseNotificationService)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $entry = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->with(['config.workingDays', 'config.breakPeriods'])
                ->find($entryId);

            if (!$entry) {
                return $this->errorResponse('Timetable entry not found in your institution.', 404);
            }

            $previousTeacherId = $entry->teacher_id;
            $data = $request->validated();
            if (empty($data)) {
                return $this->errorResponse('At least one timetable entry field is required for update.', 422);
            }

            $entry->fill(array_filter([
                'teacher_id' => $data['teacher_id'] ?? null,
                'classroom_id' => $data['classroom_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'weekday' => $data['weekday'] ?? null,
                'period_number' => $data['period_number'] ?? null,
                'start_time' => isset($data['start_time']) ? $data['start_time'] . ':00' : null,
                'end_time' => isset($data['end_time']) ? $data['end_time'] . ':00' : null,
            ], fn($value) => $value !== null));

            if (array_key_exists('is_locked', $data)) {
                $entry->is_locked = (bool) $data['is_locked'];
            } else {
                $entry->is_locked = true;
            }

            $this->validateUpdatedEntry($entry);
            $entry->save();
            $entry->load(['subject', 'teacher', 'classroom']);
            if ($entry->config) {
                $this->notifyTeacherIdsAboutTimetableUpdate(collect([$previousTeacherId, $entry->teacher_id])->filter()->unique()->values(), $entry->config, $firebaseNotificationService, [
                    'change_type' => 'manual_update',
                    'entry_ids' => [$entry->id],
                ]);
            }

            return $this->successResponse(new TimetableEntryResource($entry), 'Timetable entry updated successfully.');
        } catch (ValidationException $e) {
            return $this->generationValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function resolveConfig(array $payload, int $institutionId): SchoolTimetableConfig
    {
        $query = SchoolTimetableConfig::query()
            ->where('institution_id', $institutionId)
            ->with(['workingDays', 'breakPeriods']);

        $config = isset($payload['config_id'])
            ? $query->whereKey($payload['config_id'])->first()
            : $query->where('is_active', true)->latest()->first();

        if (!$config) {
            throw ValidationException::withMessages([
                'config' => ['No active timetable configuration was found for this institution.'],
            ]);
        }

        return $config;
    }

    // private function groupEntries(array $entries, TimetableEntryResolver $resolver): array
    // {
    //     return collect($entries)
    //         ->groupBy('weekday')
    //         ->map(function ($dayEntries, $weekday) use ($resolver) {
    //             return [
    //                 'weekday' => (int) $weekday,
    //                 'weekday_name' => $resolver->weekdayName((int) $weekday),
    //                 'entries' => array_values($dayEntries->all()),
    //             ];
    //         })
    //         ->values()
    //         ->all();
    // }

    private function groupEntries(array $entries, TimetableEntryResolver $resolver, ?SchoolTimetableConfig $config = null): array
    {
        $displayEntries = collect($entries)
            ->merge($this->breakDisplayEntries($config));

        return $displayEntries
            ->groupBy('weekday')
            ->map(function ($dayEntries, $weekday) use ($resolver) {
                $sorted = $dayEntries
                    ->sortBy(fn ($entry) => implode('|', [
                        $entry['sort_start_time'] ?? date('H:i:s', strtotime($entry['start_time'] ?? '00:00:00')),
                        $entry['sort_end_time'] ?? date('H:i:s', strtotime($entry['end_time'] ?? '00:00:00')),
                        $entry['entry_type'] ?? 'lesson',
                        str_pad((string) ($entry['period_number'] ?? 0), 4, '0', STR_PAD_LEFT),
                        str_pad((string) data_get($entry, 'classroom.id', 0), 10, '0', STR_PAD_LEFT),
                    ]))
                    ->map(function ($entry) {
                        unset($entry['sort_start_time'], $entry['sort_end_time']);
                        return $entry;
                    })
                    ->values();

                return [
                    'weekday' => (int) $weekday,
                    'weekday_name' => $resolver->weekdayName((int) $weekday),
                    'entries' => $sorted->all(),
                ];
            })
            ->sortBy('weekday')
            ->values()
            ->all();
    }

    private function breakDisplayEntries(?SchoolTimetableConfig $config): array
    {
        if (!$config) {
            return [];
        }

        $workingDays = $config->workingDays
            ->filter(fn ($day) => (bool) $day->is_open)
            ->pluck('weekday')
            ->map(fn ($weekday) => (int) $weekday)
            ->values();

        return $config->breakPeriods
            ->flatMap(function ($break) use ($workingDays) {
                $weekdays = $break->weekday
                    ? collect([(int) $break->weekday])
                    : $workingDays;

                return $weekdays->map(fn (int $weekday) => [
                    'id' => null,
                    'config_id' => $break->config_id,
                    'academic_year' => null,
                    'term' => null,
                    'weekday' => $weekday,
                    'weekday_name' => null,
                    'period_number' => null,
                    'start_time' => date('h:i a', strtotime($break->start_time)),
                    'end_time' => date('h:i a', strtotime($break->end_time)),
                    'sort_start_time' => $break->start_time,
                    'sort_end_time' => $break->end_time,
                    'entry_type' => 'break',
                    'is_break' => true,
                    'break' => [
                        'id' => $break->id,
                        'name' => $break->name,
                        'break_type' => $break->break_type ?? 'break',
                    ],
                    'subject' => null,
                    'teacher' => null,
                    'classroom' => null,
                ]);
            })
            ->values()
            ->all();
    }

    private function validateUpdatedEntry(TimetableEntry $entry, array $ignoreIds = []): void
    {
        $conflictExists = TimetableEntry::query()
            ->where('institution_id', $entry->institution_id)
            ->where('config_id', $entry->config_id)
            ->whereNotIn('id', array_values(array_unique(array_merge([$entry->id], $ignoreIds))))
            ->where('weekday', $entry->weekday)
            ->where(function ($query) use ($entry) {
                $query->where('teacher_id', $entry->teacher_id)
                    ->orWhere('classroom_id', $entry->classroom_id);
            })
            ->where(function ($query) use ($entry) {
                $query->where('start_time', '<', $entry->end_time)
                    ->where('end_time', '>', $entry->start_time);
            })
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'entry' => ['The requested timetable update causes a teacher or classroom conflict.'],
            ]);
        }

        $config = $entry->config;
        if ($config && !$this->slotFitsConfig($entry, $config)) {
            throw ValidationException::withMessages([
                'entry' => ['The requested timetable update falls outside the configured working day or overlaps a break period.'],
            ]);
        }
    }

    private function slotFitsConfig(TimetableEntry $entry, SchoolTimetableConfig $config): bool
    {
        $workingDay = $config->workingDays->first(fn($day) => (int) $day->weekday === (int) $entry->weekday && $day->is_open);
        if (!$workingDay) {
            return false;
        }

        $startTime = strlen($entry->start_time) === 5 ? $entry->start_time . ':00' : $entry->start_time;
        $endTime = strlen($entry->end_time) === 5 ? $entry->end_time . ':00' : $entry->end_time;
        $configStart = strlen($config->school_start_time) === 5 ? $config->school_start_time . ':00' : $config->school_start_time;
        $configEnd = strlen($config->school_end_time) === 5 ? $config->school_end_time . ':00' : $config->school_end_time;

        if ($startTime < $configStart || $endTime > $configEnd) {
            return false;
        }

        foreach ($config->breakPeriods as $break) {
            if ($break->weekday !== null && (int) $break->weekday !== (int) $entry->weekday) {
                continue;
            }

            $breakStart = strlen($break->start_time) === 5 ? $break->start_time . ':00' : $break->start_time;
            $breakEnd = strlen($break->end_time) === 5 ? $break->end_time . ':00' : $break->end_time;
            if (!($endTime <= $breakStart || $startTime >= $breakEnd)) {
                return false;
            }
        }

        return true;
    }

    private function notifyTeachersAboutUpdatedTimetable(Collection $entries, FirebaseNotificationService $firebaseNotificationService, SchoolTimetableConfig $config): void
    {
        $teacherEntries = $entries->filter(fn(TimetableEntry $entry) => !empty($entry->teacher_id))->groupBy('teacher_id');

        foreach ($teacherEntries as $items) {
            $this->notifyTeacherIdsAboutTimetableUpdate(collect([$items->first()?->teacher_id])->filter()->unique()->values(), $config, $firebaseNotificationService, [
                'subject_ids' => $items->pluck('subject_id')->unique()->values()->all(),
                'classroom_ids' => $items->pluck('classroom_id')->unique()->values()->all(),
                'entries_count' => $items->count(),
                'change_type' => 'apply',
                'entry_ids' => $items->pluck('id')->all(),
            ]);
        }
    }

    private function notifyTeacherIdsAboutTimetableUpdate(Collection $teacherIds, SchoolTimetableConfig $config, FirebaseNotificationService $firebaseNotificationService, array $meta = []): void
    {
        $teachers = User::query()
            ->whereIn('id', $teacherIds->filter()->unique()->values())
            ->get();

        foreach ($teachers as $teacher) {
            $title = 'Timetable Updated';
            $message = 'Your subject timetable has been updated. Please view your class subjects timetable.';
            $notificationMeta = array_merge([
                'teacher_id' => $teacher->id,
                'config_id' => $config->id,
                'academic_year' => $config->academic_year,
                'term' => $config->term,
            ], $meta);

            $log = NotificationLog::create([
                'user_id' => $teacher->id,
                'type' => 'timetable_updated',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'meta' => $notificationMeta,
            ]);

            if ($teacher->notifications_enabled && $teacher->fcm_token) {
                $firebaseNotificationService->sendToToken(
                    $teacher->fcm_token,
                    $title,
                    $message,
                    [
                        'type' => 'timetable_updated',
                        'notification_id' => (string) $log->id,
                        'teacher_id' => (string) $teacher->id,
                        'config_id' => (string) $config->id,
                        'change_type' => (string) ($meta['change_type'] ?? 'update'),
                    ]
                );
            }
        }
    }

    private function generationValidationErrorResponse(ValidationException $e)
    {
        $errors = $e->errors();
        $unscheduled = collect($errors['unscheduled_requirements'] ?? [])->filter(fn($item) => is_array($item))->values()->all();
        $generationSummary = collect($errors['generation_summary'] ?? [])->first(fn($item) => is_array($item));
        $general = collect($errors)
            ->except(['unscheduled_requirements', 'generation_summary'])
            ->map(fn($messages, $field) => [
                'field' => $field,
                'messages' => array_values((array) $messages),
            ])
            ->values()
            ->all();

        $message = !empty($unscheduled)
            ? 'Timetable generation failed because one or more class subject requirements could not be placed.'
            : 'Timetable generation failed due to invalid setup data.';

        return $this->errorResponse($message, 422, [
            'general' => $general,
            'unscheduled_requirements' => $unscheduled,
            'generation_summary' => $generationSummary,
        ]);
    }

    private function serializeConfig(SchoolTimetableConfig $config): array
    {
        return [
            'id' => $config->id,
            'academic_year' => $config->academic_year,
            'term' => $config->term,
            'mode' => $config->mode,
            'school_start_time' => $config->school_start_time,
            'school_end_time' => $config->school_end_time,
            'lesson_duration_minutes' => (int) $config->lesson_duration_minutes,
            'is_active' => (bool) $config->is_active,
        ];
    }
}
