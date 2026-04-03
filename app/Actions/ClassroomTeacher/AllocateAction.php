<?php

namespace App\Actions\ClassroomTeacher;

use App\Models\User;
use App\Models\Classroom;

class AllocateAction
{
    public function handle(array $data): bool
    {
        $teacher = User::find($data['teacher_id']);
        $classroom = Classroom::find($data['classroom_id']);

        if (!$teacher || !$classroom) {
            return false;
        }

        // Check if already allocated
        $alreadyAllocated = $teacher->classrooms()->where('classroom_id', $classroom->id)->exists();

        if ($alreadyAllocated) {
            return false;
        }

        $teacher->classrooms()->attach($classroom->id, [
           
            'assigned_by' => auth()->id(),
            'term'        => $data['term'],
            'year'        => $data['year'],
            'section'     => $data['section'] ?? null,
            'teacher_id'  => $data['teacher_id'],
        ]);

        return true;
    }
}