<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use App\Support\ClassSubjectRequirementSyncer;

class UpdateClassroomAction
{
    public function handle(Classroom $classroom, array $data): Classroom
    {
        $classroom->update([
            'name' => $data['name'] ?? $classroom->name,
            'code' => array_key_exists('code', $data) ? $data['code'] : $classroom->code,
            'in_charge_id' => array_key_exists('in_charge_id', $data) ? $data['in_charge_id'] : $classroom->in_charge_id,
        ]);

        if (isset($data['subjects'])) {
            $existingSubjectIds = collect($data['subjects'])->pluck('id')->filter()->toArray();

            // Treat the incoming payload as the complete classroom subject list.
            $classroom->subjects()->whereNotIn('id', $existingSubjectIds)->delete();

            foreach ($data['subjects'] as $subjectData) {
                if (!empty($subjectData['id'])) {
                    // Update existing subject
                    $classroom->subjects()->where('id', $subjectData['id'])->update([
                        'name' => $subjectData['name'],
                    ]);
                    $subject = $classroom->subjects()->find($subjectData['id']);
                } else {
                    // Create new subject
                    $subject = $classroom->subjects()->create([
                        'name' => $subjectData['name'],
                        'institution_id' => $classroom->institution_id,
                    ]);
                }

                if ($subject) {
                    app(ClassSubjectRequirementSyncer::class)->syncFromSubjectPayload($subject, $subjectData);
                }
            }
        }

        return $classroom->refresh()->load(['subjects.classSubjectRequirement.teacher']);
    }
}
