<?php

namespace App\Actions\Result;

use App\Models\FinalResultSubmission;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentResultRemark;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateStudentResultPdfAction
{
    private const TERM_ORDER = [
        'first' => 1,
        'second' => 2,
        'third' => 3,
        'final' => 4,
    ];

    /**
     * Columns printed on the report-book page, in order.
     */
    private const GRADE_COLUMNS = [
        'test_1' => 'Test 1',
        'test_2' => 'Test 2',
        'test_3' => 'Test 3',
        'exam_marks' => 'Exams',
    ];

    public function handle(Student $student, FinalResultSubmission $submission)
    {
        $student->loadMissing(['guardian', 'classroom.institution']);

        $institution = $student->classroom?->institution;
        $allowedTerms = $this->allowedTerms($submission->term->value);
        $columnTypes = array_keys(self::GRADE_COLUMNS);

        $classroomGrades = StudentGrade::with('subject')
            ->where('classroom_id', $submission->classroom_id)
            ->whereIn('term', $allowedTerms)
            ->whereIn('type', $columnTypes)
            ->whereNotNull('score')
            ->get();

        $ownGrades = StudentGrade::with('subject')
            ->where('student_id', $student->id)
            ->whereIn('term', $allowedTerms)
            ->whereIn('type', $columnTypes)
            ->whereNotNull('score')
            ->get();

        $remarks = StudentResultRemark::where('student_id', $student->id)
            ->where('classroom_id', $submission->classroom_id)
            ->get()
            ->keyBy('term');

        $classSize = Student::where('classroom_id', $submission->classroom_id)->count();

        $terms = collect($allowedTerms)
            ->map(function (string $term) use ($ownGrades, $classroomGrades, $remarks, $student, $classSize) {
                $termGrades = $ownGrades->filter(fn (StudentGrade $grade) => $grade->term->value === $term);

                if ($termGrades->isEmpty()) {
                    return null;
                }

                $subjects = $termGrades
                    ->groupBy('subject_id')
                    ->map(function ($subjectGrades) {
                        $subject = $subjectGrades->first()->subject;

                        $cells = collect(array_keys(self::GRADE_COLUMNS))->mapWithKeys(function (string $type) use ($subjectGrades) {
                            $grade = $subjectGrades->sortByDesc('id')->firstWhere('type', $type);

                            return [$type => $grade ? [
                                'score' => (float) $grade->score,
                                'total' => $grade->total !== null ? (float) $grade->total : null,
                            ] : null];
                        });

                        return [
                            'subject_name' => $subject?->name ?? 'Unknown Subject',
                            'cells' => $cells,
                            'total_score' => $cells->sum(fn ($cell) => $cell['score'] ?? 0),
                            'total_max' => $cells->sum(fn ($cell) => $cell['total'] ?? 0),
                        ];
                    })
                    ->sortBy('subject_name')
                    ->values();

                $columnTotals = collect(array_keys(self::GRADE_COLUMNS))->mapWithKeys(fn (string $type) => [
                    $type => $subjects->sum(fn ($subject) => $subject['cells'][$type]['score'] ?? 0),
                ]);

                $termScore = $subjects->sum('total_score');
                $termMax = $subjects->sum('total_max');
                $subjectCount = max($subjects->count(), 1);

                $remark = $remarks->get($term);

                return [
                    'term' => $term,
                    'label' => ucfirst($term) . ' Term',
                    'subjects' => $subjects,
                    'column_totals' => $columnTotals,
                    'column_averages' => $columnTotals->map(fn ($total) => $total / $subjectCount),
                    'total_score' => $termScore,
                    'total_max' => $termMax,
                    'total_average' => $termScore / $subjectCount,
                    'percentage' => $termMax > 0 ? round(($termScore / $termMax) * 100, 2) : null,
                    'position' => $this->position($classroomGrades, $term, $student->id),
                    'class_size' => $classSize,
                    'remark' => $remark,
                ];
            })
            ->filter()
            ->values();

        $html = view('results.student_result_sheet', [
            'student' => $student,
            'guardian' => $student->guardian,
            'classroom' => $student->classroom,
            'institution' => $institution,
            'submission' => $submission,
            'terms' => $terms,
            'columns' => self::GRADE_COLUMNS,
            'logo' => $this->imageDataUri($institution?->getRawOriginal('logo')),
            'signature' => $this->imageDataUri($institution?->getRawOriginal('principal_signature')),
            'stamp' => $this->imageDataUri($institution?->getRawOriginal('stamp')),
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

    /**
     * Rank of the student in the class for one term (ties share a position).
     */
    private function position($classroomGrades, string $term, int $studentId): ?int
    {
        $percentages = $classroomGrades
            ->filter(fn (StudentGrade $grade) => $grade->term->value === $term)
            ->groupBy('student_id')
            ->map(function ($grades) {
                $max = $grades->sum(fn (StudentGrade $grade) => (float) ($grade->total ?? 0));

                return $max > 0 ? $grades->sum(fn (StudentGrade $grade) => (float) $grade->score) / $max : 0;
            });

        if (!$percentages->has($studentId)) {
            return null;
        }

        $own = $percentages->get($studentId);

        return $percentages->filter(fn ($value) => $value > $own)->count() + 1;
    }

    /**
     * Dompdf has remote files switched off, so uploaded images are embedded directly.
     */
    private function imageDataUri(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
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
