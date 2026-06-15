<?php

namespace App\Http\Controllers\Api\Principal;

use App\Enums\TermType;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\FinalResultSubmission;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Throwable;

class FinalResultSubmissionController extends Controller
{
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'classroom_id' => ['required', 'exists:classrooms,id'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

            $classroom = Classroom::where('institution_id', $user->institution_id)
                ->findOrFail($validated['classroom_id']);

            $latestCompletedTerm = collect(TermType::values())
                ->reverse()
                ->first(function (string $term) use ($classroom) {
                    return StudentGrade::where('classroom_id', $classroom->id)
                        ->where('term', $term)
                        ->where('type', 'exam_marks')
                        ->whereNotNull('score')
                        ->where('score', '>', 0)
                        ->exists();
                });

            if (!$latestCompletedTerm) {
                return $this->errorResponse(
                    'Final result cannot be submitted until at least one exam_marks record with score greater than zero exists for this class.',
                    422
                );
            }

            $submission = FinalResultSubmission::updateOrCreate(
                [
                    'institution_id' => $user->institution_id,
                    'classroom_id' => $classroom->id,
                    'term' => $latestCompletedTerm,
                ],
                [
                    'title' => $validated['title'] ?? 'Final Result ' . ucfirst($latestCompletedTerm) . ' Term',
                    'is_active' => true,
                    'published_at' => now(),
                    'published_by' => $user->id,
                ]
            );

            $submission->load(['classroom', 'publisher']);

            return $this->successResponse([
                'id' => $submission->id,
                'title' => $submission->title,
                'term' => $submission->term->value,
                'classroom_id' => $submission->classroom_id,
                'classroom_name' => $submission->classroom?->name,
                'published_at' => $submission->published_at,
                'published_by' => $submission->publisher?->full_name,
                'is_active' => $submission->is_active,
            ], 'Final result submitted successfully for the latest completed term.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
