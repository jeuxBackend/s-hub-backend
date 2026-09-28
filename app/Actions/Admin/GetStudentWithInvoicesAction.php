<?php

namespace App\Actions\Admin;

use App\Models\Student;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetStudentWithInvoicesAction
{
    public function handle($studentId): Student
    {
        $student = Student::with([
            'guardian',
            'guardian.authorizedPickup',
            'studentInvoices',
            'feeRecords',
            'classroom',
            'institution',
            'studentGrades',
            'attendanceRecords',
            'classroomSubjects',
        ])->findOrFail($studentId);

        return $student;
    }
}
