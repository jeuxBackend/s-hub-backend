<?php

namespace App\Actions\Dashboard;

use App\Models\StudentGrade;
use Illuminate\Support\Facades\Auth;

class GetPrincipalAcademicAnalyticsAction
{
    public function handle(array $filters = []): array
    {
        $principal = Auth::user();
        $institutionId = $principal?->institution_id;
        $classroomId = isset($filters['classroom_id']) ? (int) $filters['classroom_id'] : null;
        $limitSubjectToppers = $this->resolvePositiveInt($filters['limit_subject_toppers'] ?? null, 50);
        $includeClassRankings = $this->resolveBoolean($filters['include_class_rankings'] ?? true);

        $grades = StudentGrade::query()
            ->where('type', 'years_marks')
            ->whereHas('student', function ($query) use ($institutionId, $classroomId) {
                $query->where('institution_id', $institutionId);

                if ($classroomId) {
                    $query->where('classroom_id', $classroomId);
                }
            })
            ->with([
                'student:id,first_name,sur_name,registration_number,classroom_id,institution_id',
                'student.classroom:id,name',
                'subject:id,name',
            ])
            ->get()
            ->filter(function (StudentGrade $grade) {
                return $grade->student && $grade->subject;
            })
            ->values();

        $studentSummaries = $this->buildStudentSummaries($grades);
        $subjectToppers = $this->buildSubjectToppers($grades, $limitSubjectToppers);
        $classRankings = $includeClassRankings
            ? $this->buildClassRankings($studentSummaries)
            : [];

        $schoolTopper = $studentSummaries[0] ?? null;
        $bestClass = $classRankings[0] ?? null;
        $lowestClass = !empty($classRankings) ? $classRankings[array_key_last($classRankings)] : null;

        return [
            'filters' => [
                'classroom_id' => $classroomId,
                'grade_type' => 'years_marks',
                'limit_subject_toppers' => $limitSubjectToppers,
                'include_class_rankings' => $includeClassRankings,
            ],
            'summary' => [
                'total_students_considered' => count($studentSummaries),
                'total_classes_considered' => count($classRankings),
                'total_subjects_considered' => count($subjectToppers),
            ],
            'school_topper' => $schoolTopper,
            'best_class' => $bestClass,
            'lowest_class' => $lowestClass,
            'subject_toppers' => $subjectToppers,
            'class_rankings' => $classRankings,
            'chart_data' => [
                'subject_toppers_bar_chart' => array_map(function (array $topper) {
                    return [
                        'x' => $topper['subject_name'],
                        'y' => $topper['percentage'],
                        'student_id' => $topper['student_id'],
                        'student_name' => $topper['student_name'],
                        'classroom_id' => $topper['classroom_id'],
                        'classroom_name' => $topper['classroom_name'],
                    ];
                }, $subjectToppers),
                'class_performance_bar_chart' => array_map(function (array $classroom) {
                    return [
                        'x' => $classroom['classroom_name'],
                        'y' => $classroom['average_percentage'],
                        'classroom_id' => $classroom['classroom_id'],
                        'students_count' => $classroom['students_count'],
                        'rank' => $classroom['rank'],
                    ];
                }, $classRankings),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentSummaries($grades): array
    {
        $students = $grades
            ->groupBy('student_id')
            ->map(function ($studentGrades, $studentId) {
                /** @var \App\Models\StudentGrade $sample */
                $sample = $studentGrades->first();
                $student = $sample->student;
                $classroom = $student?->classroom;
                $obtainedMarks = round((float) $studentGrades->sum('score'), 2);
                $totalMarks = round((float) $studentGrades->sum('total'), 2);
                $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0.0;
                $studentName = trim(($student?->first_name ?? '') . ' ' . ($student?->sur_name ?? ''));

                return [
                    'student_id' => (int) $studentId,
                    'student_name' => $studentName,
                    'registration_number' => $student?->registration_number,
                    'classroom_id' => $classroom?->id,
                    'classroom_name' => $classroom?->name,
                    'obtained_marks' => $obtainedMarks,
                    'total_marks' => $totalMarks,
                    'percentage' => $percentage,
                ];
            })
            ->filter(fn(array $student) => $student['total_marks'] > 0)
            ->values()
            ->all();

        usort($students, function (array $left, array $right) {
            return $this->compareRankEntries($left, $right);
        });

        return $students;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSubjectToppers($grades, int $limitSubjectToppers): array
    {
        $subjectToppers = $grades
            ->groupBy('subject_id')
            ->map(function ($subjectGrades, $subjectId) {
                /** @var \App\Models\StudentGrade $sample */
                $sample = $subjectGrades->first();
                $subjectName = $sample->subject?->name;

                $rankedStudents = $subjectGrades
                    ->groupBy('student_id')
                    ->map(function ($studentGrades, $studentId) use ($subjectId, $subjectName) {
                        /** @var \App\Models\StudentGrade $sampleGrade */
                        $sampleGrade = $studentGrades->first();
                        $student = $sampleGrade->student;
                        $classroom = $student?->classroom;
                        $obtainedMarks = round((float) $studentGrades->sum('score'), 2);
                        $totalMarks = round((float) $studentGrades->sum('total'), 2);
                        $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0.0;

                        return [
                            'subject_id' => (int) $subjectId,
                            'subject_name' => $subjectName,
                            'student_id' => (int) $studentId,
                            'student_name' => trim(($student?->first_name ?? '') . ' ' . ($student?->sur_name ?? '')),
                            'classroom_id' => $classroom?->id,
                            'classroom_name' => $classroom?->name,
                            'obtained_marks' => $obtainedMarks,
                            'total_marks' => $totalMarks,
                            'percentage' => $percentage,
                        ];
                    })
                    ->filter(fn(array $student) => $student['total_marks'] > 0)
                    ->values()
                    ->all();

                usort($rankedStudents, function (array $left, array $right) {
                    return $this->compareRankEntries($left, $right);
                });

                return $rankedStudents[0] ?? null;
            })
            ->filter()
            ->sortBy('subject_name')
            ->values()
            ->take($limitSubjectToppers)
            ->all();

        return $subjectToppers;
    }

    /**
     * @param array<int, array<string, mixed>> $studentSummaries
     * @return array<int, array<string, mixed>>
     */
    private function buildClassRankings(array $studentSummaries): array
    {
        $classRankings = collect($studentSummaries)
            ->filter(fn(array $student) => !empty($student['classroom_id']))
            ->groupBy('classroom_id')
            ->map(function ($students, $classroomId) {
                $students = $students->values();
                $averagePercentage = round((float) $students->avg('percentage'), 2);

                return [
                    'classroom_id' => (int) $classroomId,
                    'classroom_name' => $students->first()['classroom_name'],
                    'average_percentage' => $averagePercentage,
                    'students_count' => $students->count(),
                ];
            })
            ->values()
            ->all();

        usort($classRankings, function (array $left, array $right) {
            if ($left['average_percentage'] !== $right['average_percentage']) {
                return $right['average_percentage'] <=> $left['average_percentage'];
            }

            if ($left['students_count'] !== $right['students_count']) {
                return $right['students_count'] <=> $left['students_count'];
            }

            return strcmp((string) $left['classroom_name'], (string) $right['classroom_name']);
        });

        foreach ($classRankings as $index => &$classroom) {
            $classroom['rank'] = $index + 1;
        }

        return $classRankings;
    }

    /**
     * Sort by percentage desc, obtained marks desc, student name asc, student id asc.
     */
    private function compareRankEntries(array $left, array $right): int
    {
        if ($left['percentage'] !== $right['percentage']) {
            return $right['percentage'] <=> $left['percentage'];
        }

        if ($left['obtained_marks'] !== $right['obtained_marks']) {
            return $right['obtained_marks'] <=> $left['obtained_marks'];
        }

        $nameComparison = strcmp((string) $left['student_name'], (string) $right['student_name']);
        if ($nameComparison !== 0) {
            return $nameComparison;
        }

        return ((int) $left['student_id']) <=> ((int) $right['student_id']);
    }

    private function resolvePositiveInt($value, int $default): int
    {
        $resolved = filter_var($value, FILTER_VALIDATE_INT);

        return $resolved && $resolved > 0 ? $resolved : $default;
    }

    private function resolveBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? false;
    }
}
