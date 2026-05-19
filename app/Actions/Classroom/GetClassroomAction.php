<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

class GetClassroomAction
{
    public function handle(int $id, $requester): Classroom
    {
        return Classroom::query()
            ->where('institution_id', $requester->institution->id)
            ->with(['inCharge', 'subjects.teacher', 'teachers', 'students'])
            ->findOrFail($id);
    }
}
