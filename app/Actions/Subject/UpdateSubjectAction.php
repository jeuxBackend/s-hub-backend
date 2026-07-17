<?php

namespace App\Actions\Subject;

use App\Models\Subject;
use App\Support\ClassSubjectRequirementSyncer;

class UpdateSubjectAction
{
    public function handle(Subject $subject, array $data): Subject
    {
        $subjectData = collect($data)
            ->except(['teacher_id', 'lectures_per_week', 'start_time', 'end_time'])
            ->all();

        if (!empty($subjectData)) {
            $subject->update($subjectData);
        }

        app(ClassSubjectRequirementSyncer::class)->syncFromSubjectPayload($subject->refresh(), $data);

        return $subject->refresh()->load(['classSubjectRequirement.teacher', 'classroom']);
    }
}
