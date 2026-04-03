<?php

namespace App\Actions\Student;

use App\Models\Student;

class DeleteStudentAction
{
    public function handle(Student $student): void
    {
        $student->delete();
    }
}
