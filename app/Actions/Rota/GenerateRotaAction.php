<?php

namespace App\Actions\Rota;

use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Support\TimetableEntryResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GenerateRotaAction
{
    public function handle(array $payload, int $institutionId): array
    {
        $subjectIds = collect($payload['subject_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $workingDays = collect($payload['working_days'])->map(fn ($day) => (int) $day)->unique()->sort()->values();
        $lectureDurationMinutes = (int) $payload['lecture_duration_minutes'];

        $subjects = Subject::query()
            ->where('institution_id', $institutionId)
            ->whereIn('id', $subjectIds)
            ->with(['classSubjectRequirement.teacher', 'classroom', 'institution'])
            ->get();

        if ($subjects->count() !== $subjectIds->count()) {
            throw ValidationException::withMessages([
                'subject_ids' => ['One or more subjects do not belong to your institution.'],
            ]);
        }

        $slotMap = $this->buildSlots(
            $workingDays->all(),
            $payload['school_start_time'],
            $payload['school_end_time'],
            $lectureDurationMinutes
        );

        if (empty($slotMap)) {
            throw ValidationException::withMessages([
                'school_start_time' => ['The provided time window does not produce any lecture slots.'],
            ]);
        }

        $this->validateSubjects($subjects, $workingDays->count());

        $teacherFrequency = $subjects->groupBy(fn (Subject $subject) => $subject->classSubjectRequirement?->teacher_id)->map->count();
        $classroomFrequency = $subjects->groupBy('classroom_id')->map->count();

        $sortedSubjects = $subjects->sort(function (Subject $a, Subject $b) use ($teacherFrequency, $classroomFrequency) {
            $aRequirement = $a->classSubjectRequirement;
            $bRequirement = $b->classSubjectRequirement;

            return (($bRequirement?->lessons_per_week ?? 1) <=> ($aRequirement?->lessons_per_week ?? 1))
                ?: (($teacherFrequency[$bRequirement?->teacher_id] ?? 0) <=> ($teacherFrequency[$aRequirement?->teacher_id] ?? 0))
                ?: (($classroomFrequency[$b->classroom_id] ?? 0) <=> ($classroomFrequency[$a->classroom_id] ?? 0))
                ?: ($a->id <=> $b->id);
        })->values();

        $teacherOccupied = [];
        $classroomOccupied = [];
        $subjectOccupied = [];

        $existingEntries = TimetableEntry::query()
            ->where('institution_id', $institutionId)
            ->whereNotIn('subject_id', $subjectIds)
            ->whereIn('weekday', $workingDays)
            ->get();

        foreach ($existingEntries as $entry) {
            $this->occupySlot($teacherOccupied, (int) $entry->teacher_id, (int) $entry->weekday, $entry->start_time, $entry->end_time);
            $this->occupySlot($classroomOccupied, (int) $entry->classroom_id, (int) $entry->weekday, $entry->start_time, $entry->end_time);
        }

        $generatedEntries = collect();
        $unscheduledSubjects = [];

        foreach ($sortedSubjects as $subject) {
            $requirement = $subject->classSubjectRequirement;
            $teacherId = (int) $requirement?->teacher_id;
            $teacher = $requirement?->teacher;
            $scheduledForSubject = 0;
            $requiredLectures = (int) ($requirement?->lessons_per_week ?? 1);

            foreach (range(1, $requiredLectures) as $occurrence) {
                $placed = false;
                $availableDays = $workingDays
                    ->reject(fn (int $weekday) => isset($subjectOccupied[$subject->id][$weekday]))
                    ->values();

                foreach ($availableDays as $weekday) {
                    foreach ($slotMap[$weekday] as $slot) {
                        if (
                            $this->hasOverlap($teacherOccupied, $teacherId, $weekday, $slot['start_time'], $slot['end_time']) ||
                            $this->hasOverlap($classroomOccupied, (int) $subject->classroom_id, $weekday, $slot['start_time'], $slot['end_time'])
                        ) {
                            continue;
                        }

                        $generatedEntries->push([
                            'institution_id' => $institutionId,
                            'subject_id' => $subject->id,
                            'classroom_id' => $subject->classroom_id,
                            'teacher_id' => $teacherId,
                            'weekday' => $weekday,
                            'start_time' => $slot['start_time'],
                            'end_time' => $slot['end_time'],
                            'subject_name' => $subject->name,
                            'teacher_name' => $teacher?->full_name,
                            'classroom_name' => $subject->classroom?->name,
                            'lectures_per_week' => $requiredLectures,
                        ]);

                        $this->occupySlot($teacherOccupied, $teacherId, $weekday, $slot['start_time'], $slot['end_time']);
                        $this->occupySlot($classroomOccupied, (int) $subject->classroom_id, $weekday, $slot['start_time'], $slot['end_time']);
                        $subjectOccupied[$subject->id][$weekday] = true;

                        $scheduledForSubject++;
                        $placed = true;
                        break 2;
                    }
                }

                if (!$placed) {
                    $unscheduledSubjects[] = [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'teacher_id' => $teacherId,
                        'teacher_name' => $teacher?->full_name,
                        'classroom_id' => $subject->classroom_id,
                        'classroom_name' => $subject->classroom?->name,
                        'required_lectures_per_week' => $requiredLectures,
                        'scheduled_lectures' => $scheduledForSubject,
                        'remaining_lectures' => max($requiredLectures - $scheduledForSubject, 0),
                        'reason' => 'No conflict-free slot is available for the remaining lecture(s).',
                    ];
                    break;
                }
            }

            if ($scheduledForSubject !== $requiredLectures) {
                break;
            }
        }

        if (!empty($unscheduledSubjects)) {
            throw ValidationException::withMessages([
                'rota' => ['Unable to generate a conflict-free timetable for all selected subjects.'],
                'unscheduled_subjects' => $unscheduledSubjects,
            ]);
        }

        $generatedEntries = $generatedEntries
            ->sortBy([
                ['weekday', 'asc'],
                ['start_time', 'asc'],
                ['subject_id', 'asc'],
            ])
            ->values();

        return [
            'entries' => $generatedEntries->all(),
            'grouped_entries' => $this->groupEntries($generatedEntries),
        ];
    }

    private function buildSlots(array $workingDays, string $schoolStartTime, string $schoolEndTime, int $lectureDurationMinutes): array
    {
        $start = Carbon::createFromFormat('H:i', $schoolStartTime);
        $end = Carbon::createFromFormat('H:i', $schoolEndTime);

        $slotMap = [];

        foreach ($workingDays as $weekday) {
            $cursor = $start->copy();
            while ($cursor->copy()->addMinutes($lectureDurationMinutes)->lessThanOrEqualTo($end)) {
                $slotMap[$weekday][] = [
                    'start_time' => $cursor->format('H:i:s'),
                    'end_time' => $cursor->copy()->addMinutes($lectureDurationMinutes)->format('H:i:s'),
                ];
                $cursor->addMinutes($lectureDurationMinutes);
            }
        }

        return array_filter($slotMap);
    }

    private function validateSubjects(Collection $subjects, int $workingDayCount): void
    {
        $messages = [];

        foreach ($subjects as $subject) {
            $requirement = $subject->classSubjectRequirement;

            if (!$requirement?->teacher_id) {
                $messages['subject_ids'][] = "Subject {$subject->name} does not have a teacher assigned.";
            }

            if (!$subject->classroom_id) {
                $messages['subject_ids'][] = "Subject {$subject->name} does not have a classroom assigned.";
            }

            $lecturesPerWeek = (int) ($requirement?->lessons_per_week ?? 1);
            if ($lecturesPerWeek < 1) {
                $messages['subject_ids'][] = "Subject {$subject->name} must have at least one lecture per week.";
            }

            if ($lecturesPerWeek > $workingDayCount) {
                $messages['subject_ids'][] = "Subject {$subject->name} cannot be scheduled more than once per day in v1, so lectures_per_week must not exceed the number of working days.";
            }
        }

        if (!empty($messages)) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function occupySlot(array &$occupied, int $ownerId, int $weekday, string $startTime, string $endTime): void
    {
        $occupied[$ownerId][$weekday][] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    private function hasOverlap(array $occupied, int $ownerId, int $weekday, string $startTime, string $endTime): bool
    {
        foreach ($occupied[$ownerId][$weekday] ?? [] as $range) {
            if (!($endTime <= $range['start_time'] || $startTime >= $range['end_time'])) {
                return true;
            }
        }

        return false;
    }

    private function groupEntries(Collection $entries): array
    {
        $resolver = app(TimetableEntryResolver::class);

        return $entries
            ->groupBy('weekday')
            ->map(function (Collection $dayEntries, int $weekday) use ($resolver) {
                return [
                    'weekday' => (int) $weekday,
                    'weekday_name' => $resolver->weekdayName((int) $weekday),
                    'entries' => $dayEntries->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
