<?php

namespace App\Actions\Grade;

use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateGradeAction
{
   public function handle(StudentGrade $grade, array $data): StudentGrade
    {
        $gradeData = $data;
        
        // Handle file upload if present
        if (isset($data['file']) && $data['file']) {
            // Delete old file if exists
            if ($grade->file_path) {
                Storage::disk('public')->delete($grade->file_path);
            }
            
            $filename = time() . '_' . $data['file']->getClientOriginalName();
            $path = $data['file']->storeAs('grades', $filename, 'public');
            
            $gradeData['file_path'] = $path;
            $gradeData['file_original_name'] = $data['file']->getClientOriginalName();
        }

        $grade->update($gradeData);

        if (!in_array($grade->type, ['final_marks', 'years_marks', 'mock_exam'])) {
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