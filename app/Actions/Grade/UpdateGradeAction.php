<?php

namespace App\Actions\Grade;

use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class UpdateGradeAction
{
   public function handle(StudentGrade $grade, array $data): StudentGrade
    {
        $grade->update($data);

        if (!in_array($grade->type, ['final_marks', 'years_marks'])) {
            app(\App\Actions\Grade\CalculateStudentGradesAction::class)->handle(
                $grade->student_id,
                $grade->classroom_id,
                $grade->subject_id,
                $grade->term
            );
        }

        return $grade;
    }
}
