<?php

namespace App\Actions\Result;

use App\Models\FinalResultSubmission;
use App\Models\Student;
use App\Models\StudentGrade;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateStudentResultPdfAction
{
    private const TERM_ORDER = [
        'first' => 1,
        'second' => 2,
        'third' => 3,
        'final' => 4,
    ];

    private const GRADE_TYPE_ORDER = [
        'test_1',
        'test_2',
        'test_3',
        'test_4',
        'assignment',
        'quiz',
        'final_marks',
        'exam_marks',
        'years_marks',
        'exam',
    ];

    public function handle(Student $student, FinalResultSubmission $submission)
    {
        $student->loadMissing(['guardian', 'classroom.institution']);

        $allowedTerms = $this->allowedTerms($submission->term->value);

        $grades = StudentGrade::with('subject')
            ->where('student_id', $student->id)
            ->whereIn('term', $allowedTerms)
            ->whereNotNull('score')
            ->orderBy('subject_id')
            ->get();

        $terms = collect($allowedTerms)
            ->map(function (string $term) use ($grades) {
                $termGrades = $grades->where('term', $term)->values();

                if ($termGrades->isEmpty()) {
                    return null;
                }

                $subjects = $termGrades
                    ->groupBy('subject_id')
                    ->map(function ($subjectGrades) {
                        $orderedGrades = $subjectGrades
                            ->sortBy(fn (StudentGrade $grade) => array_search($grade->type, self::GRADE_TYPE_ORDER, true))
                            ->values();

                        $subject = $orderedGrades->first()->subject;

                        $typeCells = collect(self::GRADE_TYPE_ORDER)->mapWithKeys(function (string $type) use ($orderedGrades) {
                            $grade = $orderedGrades->firstWhere('type', $type);

                            return [
                                $type => $grade ? [
                                    'score' => (float) $grade->score,
                                    'total' => $grade->total !== null ? (float) $grade->total : null,
                                    'remarks' => $grade->remarks,
                                ] : null,
                            ];
                        });

                        $totalScore = $orderedGrades->sum(fn (StudentGrade $grade) => (float) $grade->score);
                        $totalMax = $orderedGrades->sum(fn (StudentGrade $grade) => (float) ($grade->total ?? 0));

                        return [
                            'subject_name' => $subject?->name ?? 'Unknown Subject',
                            'grades' => $typeCells,
                            'total_score' => $totalScore,
                            'total_max' => $totalMax,
                            'percentage' => $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : null,
                        ];
                    })
                    ->sortBy('subject_name')
                    ->values();

                $termScore = $subjects->sum('total_score');
                $termMax = $subjects->sum('total_max');

                return [
                    'term' => $term,
                    'label' => ucfirst($term) . ' Term',
                    'subjects' => $subjects,
                    'total_score' => $termScore,
                    'total_max' => $termMax,
                    'percentage' => $termMax > 0 ? round(($termScore / $termMax) * 100, 2) : null,
                ];
            })
            ->filter()
            ->values();

        $overallScore = $terms->sum('total_score');
        $overallMax = $terms->sum('total_max');

        $html = view('results.student_result_sheet', [
            'student' => $student,
            'guardian' => $student->guardian,
            'classroom' => $student->classroom,
            'institution' => $student->classroom?->institution,
            'submission' => $submission,
            'terms' => $terms,
            'gradeTypes' => self::GRADE_TYPE_ORDER,
            'overallScore' => $overallScore,
            'overallMax' => $overallMax,
            'overallPercentage' => $overallMax > 0 ? round(($overallScore / $overallMax) * 100, 2) : null,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'result_sheet_' . $student->id . '_' . $submission->term->value . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function allowedTerms(string $publishedTerm): array
    {
        $maxOrder = self::TERM_ORDER[$publishedTerm] ?? 0;

        return collect(self::TERM_ORDER)
            ->filter(fn (int $order) => $order <= $maxOrder)
            ->keys()
            ->values()
            ->all();
    }
}
