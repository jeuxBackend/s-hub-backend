<?php

namespace App\Actions\ClassroomTeacher;

use App\Models\User;
use App\Models\Classroom;

class AllocateAction
{
    public function handle(array $data): bool
    {
        $classroom = Classroom::find($data['classroom_id']);
        if (!$classroom) {
            return false;
        }

        $teacherIds = $data['teacher_ids'] ?? [$data['teacher_id']];
        $success = false;

        foreach ($teacherIds as $teacherId) {
            $teacher = User::find($teacherId);
            if (!$teacher) continue;

            // Check if already allocated
            $alreadyAllocated = $teacher->classrooms()->where('classroom_id', $classroom->id)->exists();

            if (!$alreadyAllocated) {
                $teacher->classrooms()->attach($classroom->id, [
                    'assigned_by' => auth()->id(),
                    'term'        => $data['term'] ?? null,
                    'year'        => $data['year'] ?? date('Y'),
                    'section'     => $data['section'] ?? null,
                ]);
                $success = true;
            }
        }

        return $success;
    }
}