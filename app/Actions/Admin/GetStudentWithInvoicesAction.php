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
        ])->findOrFail($studentId);

        return $student;
    }
}
