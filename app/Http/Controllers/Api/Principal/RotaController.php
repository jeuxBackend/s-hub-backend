<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\Rota\GenerateRotaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rota\ManageRotaRequest;
use App\Http\Resources\TimetableEntryResource;
use App\Models\NotificationLog;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Support\TimetableEntryResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RotaController extends Controller
{
    public function preview(ManageRotaRequest $request, GenerateRotaAction $generateRota, TimetableEntryResolver $resolver)
    {
        try {
            $institutionId = auth()->user()->institution_id;
            $result = $generateRota->handle($request->validated(), $institutionId);

            $entries = collect($result['entries'])->map(function (array $entry) {
                return new TimetableEntry($entry);
            });

            $resource = TimetableEntryResource::collection($entries);

            return $this->successResponse(
                [
                    'entries' => $resource,
                    'grouped_entries' => $this->groupEntries($resource->resolve(), $resolver),
                ],
                'Rota preview generated successfully.'
            );
        } catch (ValidationException $e) {
            return $this->rotaValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function apply(
        ManageRotaRequest $request,
        GenerateRotaAction $generateRota,
        TimetableEntryResolver $resolver,
        FirebaseNotificationService $firebaseNotificationService
    ) {
        try {
            $institutionId = auth()->user()->institution_id;
            $payload = $request->validated();
            $result = $generateRota->handle($payload, $institutionId);

            DB::transaction(function () use ($payload, $result, $institutionId) {
                TimetableEntry::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('subject_id', $payload['subject_ids'])
                    ->delete();

                $timestamp = now();
                TimetableEntry::insert(array_map(function (array $entry) use ($timestamp) {
                    $entry = Arr::only($entry, [
                        'institution_id',
                        'subject_id',
                        'classroom_id',
                        'teacher_id',
                        'weekday',
                        'start_time',
                        'end_time',
                    ]);

                    return [
                        ...$entry,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }, $result['entries']));
            });

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('subject_id', $payload['subject_ids'])
                ->with(['subject', 'teacher', 'classroom'])
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get();

            $this->notifyTeachersAboutUpdatedRota($entries, $firebaseNotificationService);

            $resource = TimetableEntryResource::collection($entries);

            return $this->successResponse(
                [
                    'entries' => $resource,
                    'grouped_entries' => $this->groupEntries($resource->resolve(), $resolver),
                ],
                'Rota applied successfully.'
            );
        } catch (ValidationException $e) {
            return $this->rotaValidationErrorResponse($e);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
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

    private function notifyTeachersAboutUpdatedRota(Collection $entries, FirebaseNotificationService $firebaseNotificationService): void
    {
        $teacherEntries = $entries
            ->filter(fn (TimetableEntry $entry) => !empty($entry->teacher_id))
            ->groupBy('teacher_id');

        foreach ($teacherEntries as $items) {
            $teacher = $items->first()?->teacher;

            if (!$teacher instanceof User) {
                continue;
            }

            $subjects = $items->pluck('subject.name')->filter()->unique()->values();
            $classrooms = $items->pluck('classroom.name')->filter()->unique()->values();

            $title = 'Timetable Updated';
            $message = 'Your subject timetable has been updated. Please view your class subjects timetable.';

            $log = NotificationLog::create([
                'user_id' => $teacher->id,
                'type' => 'timetable_updated',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'meta' => [
                    'teacher_id' => $teacher->id,
                    'subject_ids' => $items->pluck('subject_id')->unique()->values()->all(),
                    'subject_names' => $subjects->all(),
                    'classroom_ids' => $items->pluck('classroom_id')->unique()->values()->all(),
                    'classroom_names' => $classrooms->all(),
                    'entries_count' => $items->count(),
                ],
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
                    ]
                );
            }
        }
    }

    private function rotaValidationErrorResponse(ValidationException $e)
    {
        $errors = $e->errors();
        $unscheduledSubjects = collect($errors['unscheduled_subjects'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();

        $generalErrors = collect($errors)
            ->except(['unscheduled_subjects'])
            ->map(function ($messages, $field) {
                return [
                    'field' => $field,
                    'messages' => array_values((array) $messages),
                ];
            })
            ->values()
            ->all();

        $message = !empty($unscheduledSubjects)
            ? 'Rota generation failed because one or more subjects could not be placed without conflicts.'
            : 'Rota generation failed due to invalid scheduling input.';

        return $this->errorResponse($message, 422, [
            'general' => $generalErrors,
            'unscheduled_subjects' => $unscheduledSubjects,
        ]);
    }
}
