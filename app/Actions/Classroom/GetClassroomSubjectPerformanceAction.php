<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\StudentGrade;

class GetClassroomSubjectPerformanceAction
{
    /**
     * Return subject-wise performance for a given classroom, broken down by term.
     *
     * The response groups each subject's `final_marks` records into:
     * - first term
     * - second term
     * - third term
     * - final term
     */
    public function handle(int $classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);

        $terms = ['first', 'second', 'third', 'final'];

        // All subjects for this classroom
        $subjects = Subject::where('classroom_id', $classroomId)->get();

        // Term-based final marks for students in this classroom
        $grades = StudentGrade::where('classroom_id', $classroomId)
            ->where('type', 'final_marks')
            ->get();

        // Group grades by subject and term for efficient lookups
        $gradesBySubjectAndTerm = $grades->groupBy(function ($grade) {
            $term = $grade->term instanceof \UnitEnum ? $grade->term->value : $grade->term;

            return $grade->subject_id . '_' . $term;
        });

        $subjectsData = [];
        foreach ($subjects as $subject) {
            $termBreakdown = [];

            foreach ($terms as $term) {
                $subjectTermGrades = $gradesBySubjectAndTerm->get($subject->id . '_' . $term, collect());

                // Group by student so one student contributes one score per term
                $studentGrades = $subjectTermGrades->groupBy('student_id');

                $termBreakdown[$term] = $this->buildTermStats($studentGrades, $term);
            }

            $subjectsData[] = [
                'subject_id'   => $subject->id,
                'subject_name' => $subject->name,
                'terms'        => array_values($termBreakdown),
            ];
        }

        return [
            'classroom_id'   => $classroom->id,
            'classroom_name' => $classroom->name,
            'subjects'       => $subjectsData,
        ];
    }

    /**
     * Build term-level performance stats for a subject.
     */
    private function buildTermStats($studentGrades, string $term): array
    {
        $buckets = [
            'very_poor' => 0, // 0 - 20
            'poor' => 0, // 21 - 40
            'below_average' => 0, // 41 - 50
            'average' => 0, // 51 - 60
            'good' => 0, // 61 - 80
            'excellent' => 0, // 81 - 100
        ];

        $totalObtained = 0;
        $totalMax = 0;
        $studentsCount = 0;

        foreach ($studentGrades as $sGrades) {
            $studentObtained = $sGrades->sum('score');
            $studentMax = $sGrades->sum('total');

            $totalObtained += $studentObtained;
            $totalMax += $studentMax;
            $studentsCount++;

            $percentage = $studentMax > 0 ? ($studentObtained / $studentMax) * 100 : 0;

            if ($percentage > 80) {
                $buckets['excellent']++;
            } elseif ($percentage > 60) {
                $buckets['good']++;
            } elseif ($percentage > 50) {
                $buckets['average']++;
            } elseif ($percentage > 40) {
                $buckets['below_average']++;
            } elseif ($percentage > 20) {
                $buckets['poor']++;
            } else {
                $buckets['very_poor']++;
            }
        }

        return [
            'term' => $term,
            'total_students_graded' => $studentsCount,
            'average_percentage' => $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0,
            'distribution' => $buckets,
        ];
    }
}
