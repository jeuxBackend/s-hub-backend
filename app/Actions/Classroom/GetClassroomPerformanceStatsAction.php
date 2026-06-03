<?php

namespace App\Actions\Classroom;

use App\Models\StudentGrade;
use App\Models\Classroom;

class GetClassroomPerformanceStatsAction
{
    public function handle($classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);

        // Fetch all final_marks grades for students in this classroom
        $grades = StudentGrade::where('classroom_id', $classroomId)
            ->where('type', 'final_marks')
            ->get();

        $terms = ['first', 'second', 'third', 'final'];
        $stats = [];

        foreach ($terms as $term) {
            $termGrades = $grades->filter(function ($g) use ($term) {
                $val = $g->term instanceof \UnitEnum ? $g->term->value : $g->term;
                return $val === $term;
            });

            // Group by student to get overall percentage per student for this term
            $studentGrades = $termGrades->groupBy('student_id');

            $buckets = [
                'very_poor' => 0, // 0 - 20
                'poor' => 0, // 21 - 40
                'below_average' => 0, // 41 - 50
                'average' => 0, // 51 - 60
                'good' => 0, // 61 - 80
                'excellent' => 0, // 81 - 100
            ];

            $totalClassObtained = 0;
            $totalClassMax = 0;
            $studentsCount = 0;

            foreach ($studentGrades as $studentId => $sGrades) {
                $studentObtained = $sGrades->sum('score');
                $studentMax = $sGrades->sum('total');

                $totalClassObtained += $studentObtained;
                $totalClassMax += $studentMax;
                $studentsCount++;

                $percentage = $studentMax > 0 ? ($studentObtained / $studentMax) * 100 : 0;

                // Assign to bucket
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

            $classAveragePercentage = $totalClassMax > 0 ? round(($totalClassObtained / $totalClassMax) * 100, 2) : 0;

            $stats[$term] = [
                'term' => $term,
                'total_students_graded' => $studentsCount,
                'class_average_percentage' => $classAveragePercentage,
                'distribution' => $buckets
            ];
        }

        return [
            'classroom_id' => $classroom->id,
            'classroom_name' => $classroom->name,
            'performance_stats' => array_values($stats)
        ];
    }
}
