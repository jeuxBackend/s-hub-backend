<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

class DeleteClassroomAction
{
    public function handle(Classroom $classroom): void
    {
        $classroom->delete(); // soft delete if using SoftDeletes
    }
}
