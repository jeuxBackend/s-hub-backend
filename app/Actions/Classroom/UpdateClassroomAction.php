<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

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
            
            // Delete subjects that are not in the new list
            $classroom->subjects()->whereNotIn('id', $existingSubjectIds)->delete();

            foreach ($data['subjects'] as $subjectData) {
                if (!empty($subjectData['id'])) {
                    // Update existing subject
                    $classroom->subjects()->where('id', $subjectData['id'])->update([
                        'name' => $subjectData['name'],
                        'teacher_id' => $subjectData['teacher_id'] ?? null,
                        'start_time' => $subjectData['start_time'] ?? null,
                        'end_time' => $subjectData['end_time'] ?? null,
                    ]);
                } else {
                    // Create new subject
                    $classroom->subjects()->create([
                        'name' => $subjectData['name'],
                        'institution_id' => $classroom->institution_id,
                        'teacher_id' => $subjectData['teacher_id'] ?? null,
                        'start_time' => $subjectData['start_time'] ?? null,
                        'end_time' => $subjectData['end_time'] ?? null,
                    ]);
                }
            }
        }

        return $classroom->refresh();
    }
}
