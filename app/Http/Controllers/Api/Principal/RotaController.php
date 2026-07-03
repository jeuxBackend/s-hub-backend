<?php

namespace App\Http\Controllers\Api\Principal;

use App\Actions\Rota\GenerateRotaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rota\ManageRotaRequest;
use App\Http\Resources\TimetableEntryResource;
use App\Models\TimetableEntry;
use App\Support\TimetableEntryResolver;
use Illuminate\Support\Arr;
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

    public function apply(ManageRotaRequest $request, GenerateRotaAction $generateRota, TimetableEntryResolver $resolver)
    {
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
