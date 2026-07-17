<?php

namespace App\Actions\Subject;

use App\Models\Subject;
use App\Support\ClassSubjectRequirementSyncer;

class StoreSubjectAction
{
    public function handle(array $data): Subject
    {
        $subject = Subject::create([
            'name'         => $data['name'],
            'code'           => $data['code'] ?? null,
            'classroom_id'   => $data['classroom_id'],
            'institution_id' =>  auth()->user()->institution->id, // ⚠️ make sure this field exists
        ]);

        app(ClassSubjectRequirementSyncer::class)->syncFromSubjectPayload($subject, $data);

        return $subject->load(['classSubjectRequirement.teacher', 'classroom']);
    }
}
