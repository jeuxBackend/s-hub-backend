<?php

namespace App\Actions\Admin;

use App\Models\Student;

class DeleteGlobalStudentAction
{
    public function handle($id)
    {
        $student = Student::findOrFail($id);
        $student->delete();
        return true;
    }
}
