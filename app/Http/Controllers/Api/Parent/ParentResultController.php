<?php

namespace App\Http\Controllers\Api\Parent;

use App\Actions\Result\GenerateStudentResultPdfAction;
use App\Http\Controllers\Controller;
use App\Models\FinalResultSubmission;
use App\Models\Student;
use Throwable;

class ParentResultController extends Controller
{
    public function index()
    {
        try {
            $parent = auth()->user();

            $students = Student::with(['classroom', 'finalResultSubmissions' => function ($query) {
                $query->where('is_active', true)->orderByDesc('published_at');
            }])
                ->where('guardian_id', $parent->id)
                ->where('status', true)
                ->get();

            $results = $students->map(function (Student $student) {
                return [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'classroom_id' => $student->classroom_id,
                    'classroom_name' => $student->classroom?->name,
                    'available_results' => $student->finalResultSubmissions
                        ->unique(fn (FinalResultSubmission $submission) => $submission->term->value)
                        ->values()
                        ->map(function (FinalResultSubmission $submission) {
                            return [
                                'submission_id' => $submission->id,
                                'title' => $submission->title,
                                'term' => $submission->term->value,
                                'published_at' => $submission->published_at,
                                'can_download' => true,
                            ];
                        }),
                ];
            });

            return $this->successResponse($results, 'Available results retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function download(FinalResultSubmission $submission, Student $student, GenerateStudentResultPdfAction $action)
    {
        try {
            $parent = auth()->user();

            $student->loadMissing('classroom');

            if ($student->guardian_id !== $parent->id) {
                return $this->errorResponse('Unauthorized access. This student is not registered under your profile.', 403);
            }

            if (
                !$submission->is_active ||
                $submission->classroom_id !== $student->classroom_id ||
                $submission->institution_id !== $student->institution_id
            ) {
                return $this->errorResponse('Published result not found for this student.', 404);
            }

            return $action->handle($student, $submission);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
