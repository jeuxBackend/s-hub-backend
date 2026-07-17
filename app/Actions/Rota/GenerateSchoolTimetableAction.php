<?php

namespace App\Actions\Rota;

use App\Models\ClassSubjectRequirement;
use App\Models\SchoolTimetableConfig;
use App\Models\TeacherAvailability;
use App\Models\TimetableEntry;
use App\Support\TimetableEntryResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GenerateSchoolTimetableAction
{
    private const MAX_ATTEMPTS = 18;

    private const MAX_CANDIDATE_CHOICES_PER_ATTEMPT = 3;

    public function handle(SchoolTimetableConfig $config): array
    {
        $workingDays = $config->workingDays
            ->where('is_open', true)
            ->pluck('weekday')
            ->map(fn ($day) => (int) $day)
            ->unique()
            ->sort()
            ->values();

        if ($workingDays->isEmpty()) {
            throw ValidationException::withMessages([
                'config' => ['The active timetable configuration does not have any open working days.'],
            ]);
        }

        $requirements = ClassSubjectRequirement::query()
            ->where('institution_id', $config->institution_id)
            ->where('is_active', true)
            ->with(['classroom.inCharge', 'subject', 'teacher'])
            ->get();

        if ($requirements->isEmpty()) {
            throw ValidationException::withMessages([
                'requirements' => ['No active class subject requirements were found for timetable generation.'],
            ]);
        }

        $this->resolvePrimaryModeTeachers($config, $requirements);
        $this->validateRequirements($requirements, $workingDays->count());

        $slotMap = $this->buildSlots($config, $workingDays);

        if (empty($slotMap)) {
            throw ValidationException::withMessages([
                'config' => ['The timetable configuration does not produce any valid lesson periods after applying breaks.'],
            ]);
        }

        $teacherAvailabilityMap = $this->buildTeacherAvailabilityMap($config, $workingDays);
        $lockedEntries = TimetableEntry::query()
            ->where('institution_id', $config->institution_id)
            ->where('config_id', $config->id)
            ->where('is_locked', true)
            ->get();

        $baseState = $this->buildInitialState($lockedEntries);
        $lockedEntryPayloads = $this->serializeLockedEntries($lockedEntries);
        $attempts = $this->buildAttempts($requirements, $slotMap, $teacherAvailabilityMap, $workingDays);

        $bestSuccess = null;
        $bestFailure = null;

        foreach ($attempts as $attemptIndex => $attempt) {
            $result = $this->runGreedyAttempt(
                $config,
                $attempt['requirements'],
                $slotMap,
                $teacherAvailabilityMap,
                $baseState,
                $attempt['slot_pick_offset'],
                $attemptIndex + 1
            );

            if ($result['success']) {
                if ($bestSuccess === null || $result['schedule_score'] > $bestSuccess['schedule_score']) {
                    $bestSuccess = $result;
                }

                continue;
            }

            if ($bestFailure === null || $result['scheduled_lessons'] > $bestFailure['scheduled_lessons']) {
                $bestFailure = $result;
            }
        }

        if ($bestSuccess === null) {
            throw ValidationException::withMessages([
                'timetable' => ['Unable to generate a complete conflict-free timetable for the active configuration.'],
                'unscheduled_requirements' => $bestFailure['unscheduled_requirements'] ?? [],
                'generation_summary' => [[
                    'attempts_tried' => count($attempts),
                    'scheduled_lessons' => $bestFailure['scheduled_lessons'] ?? 0,
                    'required_lessons' => (int) $requirements->sum('lessons_per_week'),
                    'reason' => $bestFailure['reason'] ?? 'No valid scheduling attempt could place all lessons.',
                ]],
            ]);
        }

        $entries = collect($bestSuccess['entries'])
            ->sortBy([
                ['weekday', 'asc'],
                ['period_number', 'asc'],
                ['classroom_id', 'asc'],
            ])
            ->values();

        $allEntries = collect($lockedEntryPayloads)
            ->merge($entries)
            ->sortBy([
                ['weekday', 'asc'],
                ['period_number', 'asc'],
                ['classroom_id', 'asc'],
            ])
            ->values();

        return [
            'config' => $config,
            'entries' => $entries->all(),
            'all_entries' => $allEntries->all(),
            'grouped_entries' => $this->groupEntries($allEntries),
            'generation_summary' => [
                'attempts_tried' => count($attempts),
                'selected_attempt' => $bestSuccess['attempt_number'],
                'generated_lessons' => $entries->count(),
                'total_lessons' => $allEntries->count(),
                'schedule_score' => $bestSuccess['schedule_score'],
                'locked_entries_preserved' => $lockedEntries->count(),
            ],
        ];
    }

    private function serializeLockedEntries(Collection $lockedEntries): array
    {
        return $lockedEntries
            ->loadMissing(['subject', 'teacher', 'classroom'])
            ->map(fn (TimetableEntry $entry) => [
                'institution_id' => $entry->institution_id,
                'config_id' => $entry->config_id,
                'academic_year' => $entry->academic_year,
                'term' => $entry->term,
                'subject_id' => $entry->subject_id,
                'classroom_id' => $entry->classroom_id,
                'teacher_id' => $entry->teacher_id,
                'weekday' => (int) $entry->weekday,
                'period_number' => (int) $entry->period_number,
                'start_time' => $this->normalizeTime($entry->start_time),
                'end_time' => $this->normalizeTime($entry->end_time),
                'entry_type' => $entry->entry_type ?? 'lesson',
                'version' => (int) ($entry->version ?? 1),
                'is_locked' => true,
                'subject_name' => $entry->subject?->name,
                'teacher_name' => $entry->teacher?->full_name,
                'classroom_name' => $entry->classroom?->name,
            ])
            ->all();
    }

    private function runGreedyAttempt(
        SchoolTimetableConfig $config,
        Collection $requirements,
        array $slotMap,
        array $teacherAvailabilityMap,
        array $baseState,
        int $slotPickOffset,
        int $attemptNumber
    ): array {
        $state = $baseState;
        $generatedEntries = [];
        $unscheduledRequirements = [];

        foreach ($requirements as $requirement) {
            $scheduledCount = (int) ($state['requirementUsage'][$this->requirementKey($requirement->subject_id, $requirement->classroom_id, $requirement->teacher_id)] ?? 0);
            $targetCount = (int) $requirement->lessons_per_week;

            if ($scheduledCount >= $targetCount) {
                continue;
            }

            while ($scheduledCount < $targetCount) {
                $remaining = $targetCount - $scheduledCount;
                $useDoublePeriod = (bool) $requirement->double_period_allowed && $remaining >= 2 && $remaining > $this->remainingFreeDaysForSubject($state, $requirement, $slotMap);
                $blockSize = $useDoublePeriod ? 2 : 1;

                $candidates = $this->findCandidatePlacements(
                    $config,
                    $requirement,
                    $slotMap,
                    $teacherAvailabilityMap,
                    $state,
                    $blockSize,
                    $attemptNumber
                );

                if (empty($candidates) && $blockSize === 2) {
                    $blockSize = 1;
                    $candidates = $this->findCandidatePlacements(
                        $config,
                        $requirement,
                        $slotMap,
                        $teacherAvailabilityMap,
                        $state,
                        $blockSize,
                        $attemptNumber
                    );
                }

                if (empty($candidates)) {
                    $unscheduledRequirements[] = [
                        ...$this->formatUnscheduledRequirement($requirement, $scheduledCount, $targetCount),
                        'attempt_number' => $attemptNumber,
                        'preferred_block_size' => $useDoublePeriod ? 2 : 1,
                    ];
                    break 2;
                }

                $choiceIndex = min($slotPickOffset, count($candidates) - 1);
                $placement = $candidates[$choiceIndex];

                foreach ($placement['slots'] as $slot) {
                    $entry = [
                        'institution_id' => $config->institution_id,
                        'config_id' => $config->id,
                        'academic_year' => $config->academic_year,
                        'term' => $config->term,
                        'subject_id' => $requirement->subject_id,
                        'classroom_id' => $requirement->classroom_id,
                        'teacher_id' => $requirement->teacher_id,
                        'weekday' => $slot['weekday'],
                        'period_number' => $slot['period_number'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'entry_type' => 'lesson',
                        'version' => 1,
                        'is_locked' => false,
                        'subject_name' => $requirement->subject?->name,
                        'teacher_name' => $requirement->teacher?->full_name,
                        'classroom_name' => $requirement->classroom?->name,
                        'lectures_per_week' => $targetCount,
                    ];

                    $generatedEntries[] = $entry;
                    $state = $this->applyPlacementToState($state, $entry);
                    $scheduledCount++;
                }
            }
        }

        if (!empty($unscheduledRequirements)) {
            return [
                'success' => false,
                'attempt_number' => $attemptNumber,
                'scheduled_lessons' => count($generatedEntries),
                'reason' => 'One or more lessons could not be placed after applying conflict checks, day spread rules, and teacher availability constraints.',
                'unscheduled_requirements' => $unscheduledRequirements,
            ];
        }

        return [
            'success' => true,
            'attempt_number' => $attemptNumber,
            'entries' => $generatedEntries,
            'scheduled_lessons' => count($generatedEntries),
            'schedule_score' => $this->calculateScheduleScore($state),
        ];
    }

    private function resolvePrimaryModeTeachers(SchoolTimetableConfig $config, Collection $requirements): void
    {
        if ($config->mode !== 'primary') {
            return;
        }

        $requirements->each(function (ClassSubjectRequirement $requirement) {
            if ($requirement->teacher_id || !$requirement->classroom?->in_charge_id) {
                return;
            }

            $requirement->teacher_id = $requirement->classroom->in_charge_id;
            $requirement->setRelation('teacher', $requirement->classroom->inCharge);
        });
    }

    private function buildAttempts(
        Collection $requirements,
        array $slotMap,
        array $teacherAvailabilityMap,
        Collection $workingDays
    ): array {
        $teacherFrequency = $requirements->groupBy('teacher_id')->map->count();
        $classroomFrequency = $requirements->groupBy('classroom_id')->map->count();
        $scarcity = [];

        foreach ($requirements as $requirement) {
            $scarcity[$requirement->id] = $this->estimateRequirementCandidateCount($requirement, $slotMap, $teacherAvailabilityMap, $workingDays);
        }

        $sorters = [
            fn (ClassSubjectRequirement $a, ClassSubjectRequirement $b) => ($scarcity[$a->id] <=> $scarcity[$b->id])
                ?: ($b->lessons_per_week <=> $a->lessons_per_week)
                ?: (($teacherFrequency[$b->teacher_id] ?? 0) <=> ($teacherFrequency[$a->teacher_id] ?? 0))
                ?: ($a->priority <=> $b->priority)
                ?: ($a->id <=> $b->id),
            fn (ClassSubjectRequirement $a, ClassSubjectRequirement $b) => (($teacherFrequency[$b->teacher_id] ?? 0) <=> ($teacherFrequency[$a->teacher_id] ?? 0))
                ?: ($scarcity[$a->id] <=> $scarcity[$b->id])
                ?: ($b->lessons_per_week <=> $a->lessons_per_week)
                ?: ($a->priority <=> $b->priority)
                ?: ($a->id <=> $b->id),
            fn (ClassSubjectRequirement $a, ClassSubjectRequirement $b) => (($classroomFrequency[$b->classroom_id] ?? 0) <=> ($classroomFrequency[$a->classroom_id] ?? 0))
                ?: ($scarcity[$a->id] <=> $scarcity[$b->id])
                ?: ((int) $b->double_period_allowed <=> (int) $a->double_period_allowed)
                ?: ($b->lessons_per_week <=> $a->lessons_per_week)
                ?: ($a->id <=> $b->id),
        ];

        $attempts = [];

        foreach ($sorters as $sorter) {
            $ordered = $requirements->sort($sorter)->values();
            $variants = [
                $ordered,
                $ordered->reverse()->values(),
            ];

            foreach ($variants as $variant) {
                for ($offset = 0; $offset < self::MAX_CANDIDATE_CHOICES_PER_ATTEMPT; $offset++) {
                    $attempts[] = [
                        'requirements' => $variant,
                        'slot_pick_offset' => $offset,
                        'signature' => $variant->pluck('id')->implode('-') . '|offset:' . $offset,
                    ];
                }
            }
        }

        return collect($attempts)
            ->unique('signature')
            ->take(self::MAX_ATTEMPTS)
            ->values()
            ->all();
    }

    private function estimateRequirementCandidateCount(
        ClassSubjectRequirement $requirement,
        array $slotMap,
        array $teacherAvailabilityMap,
        Collection $workingDays
    ): int {
        $count = 0;

        foreach ($workingDays as $weekday) {
            foreach ($slotMap[(int) $weekday] ?? [] as $slot) {
                if ($this->isTeacherAvailable((int) $requirement->teacher_id, (int) $weekday, $slot['start_time'], $slot['end_time'], $teacherAvailabilityMap)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function remainingFreeDaysForSubject(array $state, ClassSubjectRequirement $requirement, array $slotMap): int
    {
        $count = 0;

        foreach (array_keys($slotMap) as $weekday) {
            $requirementKey = $this->requirementKey($requirement->subject_id, $requirement->classroom_id, $requirement->teacher_id);

            if (($state['subjectDayUsage'][$requirementKey][(int) $weekday] ?? 0) === 0) {
                $count++;
            }
        }

        return $count;
    }

    private function buildInitialState(Collection $lockedEntries): array
    {
        $state = [
            'teacherOccupied' => [],
            'classroomOccupied' => [],
            'subjectDayUsage' => [],
            'teacherDayLoad' => [],
            'classroomDayLoad' => [],
            'teacherPeriodUsage' => [],
            'classroomPeriodUsage' => [],
            'subjectPeriodUsage' => [],
            'requirementUsage' => [],
        ];

        foreach ($lockedEntries as $entry) {
            $state = $this->applyPlacementToState($state, [
                'teacher_id' => (int) $entry->teacher_id,
                'classroom_id' => (int) $entry->classroom_id,
                'subject_id' => (int) $entry->subject_id,
                'weekday' => (int) $entry->weekday,
                'period_number' => (int) ($entry->period_number ?? 0),
                'start_time' => $entry->start_time,
                'end_time' => $entry->end_time,
            ]);
        }

        return $state;
    }

    private function applyPlacementToState(array $state, array $entry): array
    {
        $teacherId = (int) $entry['teacher_id'];
        $classroomId = (int) $entry['classroom_id'];
        $subjectId = (int) $entry['subject_id'];
        $weekday = (int) $entry['weekday'];
        $periodNumber = (int) $entry['period_number'];

        $state['teacherOccupied'][$teacherId][$weekday][] = [
            'start_time' => $entry['start_time'],
            'end_time' => $entry['end_time'],
            'period_number' => $periodNumber,
        ];
        $state['classroomOccupied'][$classroomId][$weekday][] = [
            'start_time' => $entry['start_time'],
            'end_time' => $entry['end_time'],
            'period_number' => $periodNumber,
        ];
        $requirementKey = $this->requirementKey($subjectId, $classroomId, $teacherId);

        $state['subjectDayUsage'][$requirementKey][$weekday] = (($state['subjectDayUsage'][$requirementKey][$weekday] ?? 0) + 1);
        $state['teacherDayLoad'][$teacherId][$weekday] = (($state['teacherDayLoad'][$teacherId][$weekday] ?? 0) + 1);
        $state['classroomDayLoad'][$classroomId][$weekday] = (($state['classroomDayLoad'][$classroomId][$weekday] ?? 0) + 1);
        $state['teacherPeriodUsage'][$teacherId][$weekday][$periodNumber] = true;
        $state['classroomPeriodUsage'][$classroomId][$weekday][$periodNumber] = true;
        $state['subjectPeriodUsage'][$requirementKey][$weekday][$periodNumber] = true;
        $state['requirementUsage'][$requirementKey] = (($state['requirementUsage'][$requirementKey] ?? 0) + 1);

        return $state;
    }

    private function requirementKey(int $subjectId, int $classroomId, int $teacherId): string
    {
        return $subjectId . ':' . $classroomId . ':' . $teacherId;
    }

    private function findCandidatePlacements(
        SchoolTimetableConfig $config,
        ClassSubjectRequirement $requirement,
        array $slotMap,
        array $teacherAvailabilityMap,
        array $state,
        int $blockSize,
        int $attemptNumber
    ): array {
        $candidates = [];

        foreach ($slotMap as $weekday => $slots) {
            $requirementKey = $this->requirementKey($requirement->subject_id, $requirement->classroom_id, $requirement->teacher_id);
            $currentSubjectDayCount = (int) ($state['subjectDayUsage'][$requirementKey][(int) $weekday] ?? 0);
            $maxPerDay = (bool) $requirement->double_period_allowed ? 2 : 1;

            if ($currentSubjectDayCount >= $maxPerDay) {
                continue;
            }

            foreach ($slots as $index => $slot) {
                $candidateSlots = [$slot];

                if ($blockSize === 2) {
                    $nextSlot = $slots[$index + 1] ?? null;
                    if (
                        !$nextSlot ||
                        (int) $nextSlot['period_number'] !== ((int) $slot['period_number'] + 1) ||
                        $currentSubjectDayCount > 0
                    ) {
                        continue;
                    }

                    $candidateSlots[] = $nextSlot;
                }

                if (!$this->canPlaceSlots($requirement, (int) $weekday, $candidateSlots, $teacherAvailabilityMap, $state)) {
                    continue;
                }

                $candidates[] = [
                    'weekday' => (int) $weekday,
                    'slots' => $candidateSlots,
                    'score' => $this->scorePlacement(
                        $config,
                        $requirement,
                        (int) $weekday,
                        $candidateSlots,
                        $teacherAvailabilityMap,
                        $state,
                        $attemptNumber
                    ),
                ];
            }
        }

        usort($candidates, function (array $a, array $b) {
            return ($b['score'] <=> $a['score'])
                ?: ($a['weekday'] <=> $b['weekday'])
                ?: ((int) $a['slots'][0]['period_number'] <=> (int) $b['slots'][0]['period_number']);
        });

        return $candidates;
    }

    private function canPlaceSlots(
        ClassSubjectRequirement $requirement,
        int $weekday,
        array $slots,
        array $teacherAvailabilityMap,
        array $state
    ): bool {
        foreach ($slots as $slot) {
            if (
                $this->hasOverlap($state['teacherOccupied'] ?? [], (int) $requirement->teacher_id, $weekday, $slot['start_time'], $slot['end_time']) ||
                $this->hasOverlap($state['classroomOccupied'] ?? [], (int) $requirement->classroom_id, $weekday, $slot['start_time'], $slot['end_time'])
            ) {
                return false;
            }

            if (!$this->isTeacherAvailable((int) $requirement->teacher_id, $weekday, $slot['start_time'], $slot['end_time'], $teacherAvailabilityMap)) {
                return false;
            }
        }

        return true;
    }

    private function isTeacherAvailable(int $teacherId, int $weekday, string $startTime, string $endTime, array $availabilityMap): bool
    {
        $dayRules = $availabilityMap[$teacherId][$weekday] ?? null;

        if (!$dayRules) {
            return true;
        }

        foreach ($dayRules['unavailable'] ?? [] as $range) {
            if (!$this->timeRangesDoNotOverlap($startTime, $endTime, $range['start_time'], $range['end_time'])) {
                return false;
            }
        }

        $availableRanges = $dayRules['available'] ?? [];
        if (!empty($availableRanges)) {
            foreach ($availableRanges as $range) {
                if ($startTime >= $range['start_time'] && $endTime <= $range['end_time']) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function scorePlacement(
        SchoolTimetableConfig $config,
        ClassSubjectRequirement $requirement,
        int $weekday,
        array $slots,
        array $availabilityMap,
        array $state,
        int $attemptNumber
    ): int {
        $score = 1000;
        $teacherId = (int) $requirement->teacher_id;
        $classroomId = (int) $requirement->classroom_id;
        $subjectId = (int) $requirement->subject_id;

        $score -= ((int) ($state['teacherDayLoad'][$teacherId][$weekday] ?? 0) * 28);
        $score -= ((int) ($state['classroomDayLoad'][$classroomId][$weekday] ?? 0) * 18);
        $requirementKey = $this->requirementKey($subjectId, $classroomId, $teacherId);

        $score -= ((int) ($state['subjectDayUsage'][$requirementKey][$weekday] ?? 0) * 220);

        foreach ($slots as $slot) {
            $periodNumber = (int) $slot['period_number'];
            $preferredRanges = $availabilityMap[$teacherId][$weekday]['preferred'] ?? [];

            foreach ($preferredRanges as $range) {
                if ($slot['start_time'] >= $range['start_time'] && $slot['end_time'] <= $range['end_time']) {
                    $score += 20;
                    break;
                }
            }

            if (!empty($state['teacherPeriodUsage'][$teacherId][$weekday][$periodNumber - 1] ?? false)) {
                $score -= 16;
            }

            if (!empty($state['teacherPeriodUsage'][$teacherId][$weekday][$periodNumber + 1] ?? false)) {
                $score -= 16;
            }

            if (!empty($state['subjectPeriodUsage'][$requirementKey][$weekday][$periodNumber - 1] ?? false)) {
                $score -= 40;
            }

            if (!empty($state['subjectPeriodUsage'][$requirementKey][$weekday][$periodNumber + 1] ?? false)) {
                $score -= 40;
            }

            $score -= abs(4 - $weekday) * 3;
            $score -= abs(3 - $periodNumber);
        }

        if ($config->mode === 'primary' && (int) ($requirement->classroom?->in_charge_id ?? 0) === $teacherId) {
            $score += 45;
        }

        if (count($slots) > 1) {
            $score += 12;
        }

        if ($attemptNumber % 2 === 0) {
            $score += $weekday * 2;
        }

        return $score;
    }

    private function calculateScheduleScore(array $state): int
    {
        $score = 0;

        foreach ($state['teacherDayLoad'] ?? [] as $days) {
            if (empty($days)) {
                continue;
            }

            $loads = array_values($days);
            $score -= (max($loads) - min($loads)) * 10;
        }

        foreach ($state['classroomDayLoad'] ?? [] as $days) {
            if (empty($days)) {
                continue;
            }

            $loads = array_values($days);
            $score -= (max($loads) - min($loads)) * 8;
        }

        foreach ($state['subjectDayUsage'] ?? [] as $days) {
            foreach ($days as $count) {
                if ($count > 1) {
                    $score -= ($count - 1) * 35;
                }
            }
        }

        return $score;
    }

    private function formatUnscheduledRequirement(ClassSubjectRequirement $requirement, int $scheduledLectures, int $requiredLectures): array
    {
        return [
            'requirement_id' => $requirement->id,
            'classroom_id' => $requirement->classroom_id,
            'classroom_name' => $requirement->classroom?->name,
            'subject_id' => $requirement->subject_id,
            'subject_name' => $requirement->subject?->name,
            'teacher_id' => $requirement->teacher_id,
            'teacher_name' => $requirement->teacher?->full_name,
            'required_lectures_per_week' => $requiredLectures,
            'scheduled_lectures' => $scheduledLectures,
            'remaining_lectures' => max(0, $requiredLectures - $scheduledLectures),
            'reason' => 'The scheduler could not place all required lessons without breaking teacher availability, class conflicts, or same-subject-per-day rules.',
        ];
    }

    private function validateRequirements(Collection $requirements, int $workingDayCount): void
    {
        $messages = [];

        foreach ($requirements as $requirement) {
            if (!$requirement->teacher_id) {
                $messages['requirements'][] = "Requirement {$requirement->id} for subject {$requirement->subject?->name} does not have a teacher assigned.";
            }

            if (!$requirement->classroom_id) {
                $messages['requirements'][] = "Requirement {$requirement->id} does not have a classroom assigned.";
            }

            if (!$requirement->subject_id) {
                $messages['requirements'][] = "Requirement {$requirement->id} does not have a subject assigned.";
            }

            if ((int) $requirement->lessons_per_week < 1) {
                $messages['requirements'][] = "Requirement {$requirement->id} must have at least one lesson per week.";
            }

            if (!(bool) $requirement->double_period_allowed && (int) $requirement->lessons_per_week > $workingDayCount) {
                $messages['requirements'][] = "Requirement {$requirement->id} exceeds the number of working days and must allow double periods or reduce lessons per week.";
            }
        }

        if (!empty($messages)) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function buildSlots(SchoolTimetableConfig $config, Collection $workingDays): array
    {
        $start = Carbon::createFromFormat('H:i:s', $this->normalizeTime($config->school_start_time));
        $end = Carbon::createFromFormat('H:i:s', $this->normalizeTime($config->school_end_time));
        $duration = (int) $config->lesson_duration_minutes;
        $breaksByWeekday = $config->breakPeriods->groupBy(fn ($period) => $period->weekday ? (int) $period->weekday : 0);

        $slotMap = [];

        foreach ($workingDays as $weekday) {
            $cursor = $start->copy();
            $periodNumber = 1;
            $dayBreaks = $breaksByWeekday->get((int) $weekday, collect())
                ->merge($breaksByWeekday->get(0, collect()))
                ->sortBy('start_time')
                ->values();

            while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($end)) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addMinutes($duration);
                $blockingBreak = $dayBreaks->first(function ($break) use ($slotStart, $slotEnd) {
                    $breakStart = Carbon::createFromFormat('H:i:s', $this->normalizeTime($break->start_time));
                    $breakEnd = Carbon::createFromFormat('H:i:s', $this->normalizeTime($break->end_time));

                    return !($slotEnd->lessThanOrEqualTo($breakStart) || $slotStart->greaterThanOrEqualTo($breakEnd));
                });

                if ($blockingBreak) {
                    $cursor = Carbon::createFromFormat('H:i:s', $this->normalizeTime($blockingBreak->end_time));
                    continue;
                }

                $slotMap[(int) $weekday][] = [
                    'weekday' => (int) $weekday,
                    'period_number' => $periodNumber,
                    'start_time' => $slotStart->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                ];

                $periodNumber++;
                $cursor = $slotEnd;
            }
        }

        return array_filter($slotMap);
    }

    private function buildTeacherAvailabilityMap(SchoolTimetableConfig $config, Collection $workingDays): array
    {
        $records = TeacherAvailability::query()
            ->where('institution_id', $config->institution_id)
            ->where(function ($query) use ($config) {
                $query->where('config_id', $config->id)
                    ->orWhereNull('config_id');
            })
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $map = [];

        foreach ($records as $record) {
            $map[(int) $record->teacher_id][(int) $record->weekday][$record->availability_type][] = [
                'start_time' => $this->normalizeTime($record->start_time),
                'end_time' => $this->normalizeTime($record->end_time),
            ];
        }

        foreach ($workingDays as $weekday) {
            $weekday = (int) $weekday;
            foreach ($map as $teacherId => $days) {
                $map[$teacherId][$weekday]['available'] = $days[$weekday]['available'] ?? [];
                $map[$teacherId][$weekday]['unavailable'] = $days[$weekday]['unavailable'] ?? [];
                $map[$teacherId][$weekday]['preferred'] = $days[$weekday]['preferred'] ?? [];
            }
        }

        return $map;
    }

    private function hasOverlap(array $occupied, int $ownerId, int $weekday, string $startTime, string $endTime): bool
    {
        foreach ($occupied[$ownerId][$weekday] ?? [] as $range) {
            if (!$this->timeRangesDoNotOverlap($startTime, $endTime, $range['start_time'], $range['end_time'])) {
                return true;
            }
        }

        return false;
    }

    private function timeRangesDoNotOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $endA <= $startB || $startA >= $endB;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
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
