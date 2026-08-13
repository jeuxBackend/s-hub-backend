<?php

namespace App\Actions\Grade;

use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class StoreGradeAction
{
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'] ?? null;

            // Mock exam is a standalone snapshot per subject, independent of term —
            // normalize term to null so exactly one canonical row exists per student/subject,
            // regardless of what term value (if any) the client sends.
            $term = $type === 'mock_exam' ? null : ($data['term'] ?? null);

            $gradeData = [
                'student_id'    => $data['student_id'],
                'classroom_id'  => $data['classroom_id'],
                'subject_id'    => $data['subject_id'],
                'term'          => $term,
                'type'          => $type,
                'score'         => $data['score'] ?? null,
                'total'         => $data['total'] ?? null,
                'remarks'       => $data['remarks'] ?? null,
                'date'          => $data['date'] ?? now()->toDateString(),
                'recorded_by'   => auth()->id(),
            ];

            // Handle file upload if present
            if (isset($data['file']) && $data['file']) {
                $filename = time() . '_' . $data['file']->getClientOriginalName();
                $path = $data['file']->storeAs('grades', $filename, 'public');

                $gradeData['file_path'] = $path;
                $gradeData['file_original_name'] = $data['file']->getClientOriginalName();
            }

            $grade = StudentGrade::updateOrCreate(
                [
                    'student_id'    => $data['student_id'],
                    'classroom_id'  => $data['classroom_id'],
                    'subject_id'    => $data['subject_id'],
                    'term'          => $term,
                    'type'          => $type,
                ],
                $gradeData
            );

            if (!in_array($grade->type, ['final_marks', 'years_marks', 'mock_exam'])) {
                app(\App\Actions\Grade\CalculateStudentGradesAction::class)->handle(
                    $grade->student_id,
                    $grade->classroom_id,
                    $grade->subject_id,
                    $grade->term
                );
            }

            return [$grade];
        });
    }
}
