<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use Illuminate\Support\Facades\DB;

class DeleteClassroomAction
{
    public function handle(Classroom $classroom): void
    {
        DB::transaction(function () use ($classroom) {
            $classroom->subjects()->delete();
            $classroom->delete();
        });
    }
}
