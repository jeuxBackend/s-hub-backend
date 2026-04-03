<?php

namespace App\Actions\Subject;

use App\Models\Subject;

class UpdateSubjectAction
{
    public function handle(Subject $subject, array $data): Subject
    {
        $subject->update($data);
        return $subject->refresh();
    }
}
