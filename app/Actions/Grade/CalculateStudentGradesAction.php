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

        $tests = ['test_1', 'test_2', 'test_3', 'test_4'];

        $testGrades = StudentGrade::where('student_id', $studentId)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('term', $termValue)
            ->whereIn('type', $tests)
            ->get();
            
        $examGrade = StudentGrade::where('student_id', $studentId)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('term', $termValue)
            ->where('type', 'exam_marks')
            ->first();

        // 40% Weight for Tests (Percentage based)
        $testScoreSum = $testGrades->sum('score');
        $testTotalSum = $testGrades->sum('total');

        $testPercentage = 0;
        if ($testTotalSum > 0) {
            $testPercentage = ($testScoreSum / $testTotalSum) * 40;
        }

        // 60% Weight for Exam (Percentage based)
        $examScore = $examGrade ? $examGrade->score : 0;
        $examTotal = $examGrade ? $examGrade->total : 0;

        $examPercentage = 0;
        if ($examTotal > 0) {
            $examPercentage = ($examScore / $examTotal) * 60;
        }

        // Combine to get a score out of 100
        $finalScore = $testPercentage + $examPercentage;
        $finalTotal = 100;

        if ($testTotalSum > 0 || $examTotal > 0) {
            StudentGrade::updateOrCreate(
                [
                    'student_id'   => $studentId,
                    'classroom_id' => $classroomId,
                    'subject_id'   => $subjectId,
                    'term'         => $termValue,
                    'type'         => 'final_marks',
                ],
                [
                    'score'       => round($finalScore, 2),
                    'total'       => $finalTotal,
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
