<?php

namespace App\Support;

use App\Models\ClassSubjectRequirement;
use App\Models\Subject;

class ClassSubjectRequirementSyncer
{
    public function syncFromSubjectPayload(Subject $subject, array $payload): void
    {
        ClassSubjectRequirement::query()
            ->where('institution_id', $subject->institution_id)
            ->where('subject_id', $subject->id)
            ->where('classroom_id', '!=', $subject->classroom_id)
            ->delete();

        if (!array_key_exists('teacher_id', $payload) && !array_key_exists('lectures_per_week', $payload)) {
            return;
        }

        $existing = ClassSubjectRequirement::query()
            ->where('institution_id', $subject->institution_id)
            ->where('classroom_id', $subject->classroom_id)
            ->where('subject_id', $subject->id)
            ->first();

        ClassSubjectRequirement::updateOrCreate(
            [
                'institution_id' => $subject->institution_id,
                'classroom_id' => $subject->classroom_id,
                'subject_id' => $subject->id,
            ],
            [
                'teacher_id' => array_key_exists('teacher_id', $payload)
                    ? $payload['teacher_id']
                    : $existing?->teacher_id,
                'lessons_per_week' => $payload['lectures_per_week']
                    ?? $existing?->lessons_per_week
                    ?? 1,
                'double_period_allowed' => $existing?->double_period_allowed ?? false,
                'priority' => $existing?->priority ?? 1,
                'is_active' => $existing?->is_active ?? true,
            ]
        );
    }
}
