<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Models\StudentGrade;

class GetStudentYearMarksAction
{
    public function handle(Student $student)
    {
        $yearMarksRecords = StudentGrade::where('student_id', $student->id)
            ->where('type', 'years_marks')
            ->get();

        // Get the latest record for each subject mapped by subject_id
        $latestYearMarks = $yearMarksRecords->sortByDesc('id')->keyBy('subject_id');

        // Fetch all subjects belonging to the student's classroom
        $classroomSubjects = \App\Models\Subject::where('classroom_id', $student->classroom_id)->get();

        $subjectsData = [];
        $totalObtainedSum = 0;
        $totalMaxSum = 0;

        foreach ($classroomSubjects as $subject) {
            if ($latestYearMarks->has($subject->id)) {
                $grade = $latestYearMarks->get($subject->id);
                $obtained = (float) $grade->score;
                $max = (float) $grade->total;
            } else {
                $obtained = 0;
                $max = 0;
            }

            $totalObtainedSum += $obtained;
            $totalMaxSum += $max;

            $subjectsData[] = [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'obtained_marks' => $obtained,
                'total_marks' => $max,
                'percentage' => $max > 0 ? round(($obtained / $max) * 100, 2) : 0,
            ];
        }

        $overallPercentage = 0;
        if ($totalMaxSum > 0) {
            $overallPercentage = round(($totalObtainedSum / $totalMaxSum) * 100, 2);
        }

        $performanceIndicator = 'N/A';
        if ($totalMaxSum > 0) {
            if ($overallPercentage > 70) {
                $performanceIndicator = 'good';
            } elseif ($overallPercentage > 50) {
                $performanceIndicator = 'fair';
            } else {
                $performanceIndicator = 'poor';
            }
        }

        return [
            'student_id' => $student->id,
            'student_name' => trim($student->first_name . ' ' . $student->sur_name),
            'subjects' => $subjectsData,
            'total_obtained' => $totalObtainedSum,
            'total_max' => $totalMaxSum,
            'overall_percentage' => $overallPercentage,
            'performance_indicator' => $performanceIndicator,
        ];
    }
}
