<?php

namespace App\Actions\Grade;

use App\Models\StudentGrade;
use Illuminate\Support\Facades\Log;

class CalculateStudentGradesAction
{
    public function handle(int $studentId, int $classroomId, int $subjectId, $term)
    {
        // 1. Calculate final_marks for the specific term
        $this->calculateFinalMarks($studentId, $classroomId, $subjectId, $term);

        // 2. Calculate years_marks across all terms for this subject/classroom
        $this->calculateYearsMarks($studentId, $classroomId, $subjectId);
    }

    private function calculateFinalMarks(int $studentId, int $classroomId, int $subjectId, $term)
    {
        // Extract string value if it's an Enum
        $termValue = $term instanceof \UnitEnum ? $term->value : $term;

        $components = ['test_1', 'test_2', 'test_3', 'test_4', 'exam_marks'];

        $grades = StudentGrade::where('student_id', $studentId)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('term', $termValue)
            ->whereIn('type', $components)
            ->get();

        $totalScore = $grades->sum('score');
        $totalMax = $grades->sum('total');

        if ($totalMax > 0 || $totalScore > 0) {
            StudentGrade::updateOrCreate(
                [
                    'student_id'   => $studentId,
                    'classroom_id' => $classroomId,
                    'subject_id'   => $subjectId,
                    'term'         => $termValue,
                    'type'         => 'final_marks',
                ],
                [
                    'score'       => $totalScore,
                    'total'       => $totalMax,
                    'date'        => now()->toDateString(),
                    'recorded_by' => auth()->id(),
                ]
            );
        }
    }

    private function calculateYearsMarks(int $studentId, int $classroomId, int $subjectId)
    {
        $finalMarks = StudentGrade::where('student_id', $studentId)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('type', 'final_marks')
            ->get();

        if ($finalMarks->isEmpty()) {
            return;
        }

        $termCount = $finalMarks->count();
        $totalScoreSum = $finalMarks->sum('score');
        $totalMaxSum = $finalMarks->sum('total');

        // Assuming years_marks is the average of final_marks across terms
        $averageScore = $termCount > 0 ? $totalScoreSum / $termCount : 0;
        $averageTotal = $termCount > 0 ? $totalMaxSum / $termCount : 0;

        StudentGrade::updateOrCreate(
            [
                'student_id'   => $studentId,
                'classroom_id' => $classroomId,
                'subject_id'   => $subjectId,
                'type'         => 'years_marks',
            ],
            [
                'term'        => 'final', // usually tied to the final term or just 'final'
                'score'       => $averageScore,
                'total'       => $averageTotal,
                'date'        => now()->toDateString(),
                'recorded_by' => auth()->id(),
            ]
        );
    }
}
