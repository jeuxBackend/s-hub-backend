<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\StudentGrade;

class GetClassroomSubjectPerformanceAction
{
    /**
     * Return subject-wise performance for a given classroom.
     * Uses `years_marks` records to provide a cumulative yearly view.
     */
    public function handle(int $classroomId)
    {
        $classroom = Classroom::findOrFail($classroomId);

        // All subjects for this classroom
        $subjects = Subject::where('classroom_id', $classroomId)->get();

        // All years_marks grades for students in this classroom
        $grades = StudentGrade::where('classroom_id', $classroomId)
            ->where('type', 'years_marks')
            ->get();

        // Group grades by subject_id
        $gradesBySubject = $grades->groupBy('subject_id');

        $subjectsData = [];
        foreach ($subjects as $subject) {
            $subjectGrades = $gradesBySubject->get($subject->id, collect());

            $totalObtained = 0;
            $totalMax = 0;
            $buckets = [
                'very_poor' => 0, // 0 - 20
                'poor' => 0, // 21 - 40
                'below_average' => 0, // 41 - 50
                'average' => 0, // 51 - 60
                'good' => 0, // 61 - 80
                'excellent' => 0, // 81 - 100
            ];

            foreach ($subjectGrades as $grade) {
                $obtained = (float) $grade->score;
                $max = (float) $grade->total;
                $totalObtained += $obtained;
                $totalMax += $max;

                $percentage = $max > 0 ? ($obtained / $max) * 100 : 0;

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

            $averagePercentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;

            $subjectsData[] = [
                'subject_id'        => $subject->id,
                'subject_name'      => $subject->name,
                'average_percentage'=> $averagePercentage,
                'distribution'      => $buckets,
            ];
        }

        return [
            'classroom_id'   => $classroom->id,
            'classroom_name' => $classroom->name,
            'subjects'       => $subjectsData,
        ];
    }
}
