<?php

namespace App\Actions\ClassroomTeacher;

use App\Models\User;
use App\Models\Classroom;

class UnallocateAction
{
    public function handle(array $data): bool
    {
        $teacher = User::find($data['teacher_id']);
        $classroom = Classroom::find($data['classroom_id']);

        if (!$teacher || !$classroom) {
            return false;
        }

        $exists = $teacher->classrooms()->where('classroom_id', $classroom->id)->exists();

        if (! $exists) {
            return false;
        }

        $teacher->classrooms()->detach($classroom->id);

        return true;
    }
}