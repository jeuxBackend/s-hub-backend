<?php

namespace App\Actions\Student;

use App\Models\Student;

class ToggleStudentStatusAction
{
    public function handle($id)
    {
        $student = Student::findOrFail($id);
        $student->status = !$student->status;
        $student->save();

        return $student;
    }
}
