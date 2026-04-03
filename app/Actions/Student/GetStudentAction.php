<?php

namespace App\Actions\Student;

use App\Models\Student;

class GetStudentAction
{
    public function handle(int $id): Student
    {
        return Student::with(['classroom', 'guardian', 'feeRecords', 'attendanceRecords'])
            ->findOrFail($id);
    }
}
