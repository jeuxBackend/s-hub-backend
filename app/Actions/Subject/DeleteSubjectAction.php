<?php

namespace App\Actions\Subject;

use App\Models\Subject;

class DeleteSubjectAction
{
    public function handle(Subject $subject): void
    {
        $subject->delete();
    }
}
