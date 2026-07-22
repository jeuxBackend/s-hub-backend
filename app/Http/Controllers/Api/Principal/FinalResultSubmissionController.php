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

            $completedTerms = collect(TermType::values())
                ->filter(function (string $term) use ($classroom) {
                    return StudentGrade::where('classroom_id', $classroom->id)
                        ->where('term', $term)
                        ->where('type', 'exam_marks')
                        ->whereNotNull('score')
                        ->where('score', '>', 0)
                        ->exists();
                });

            if ($completedTerms->isEmpty()) {
                return $this->errorResponse(
                    'Final result cannot be submitted until at least one completed term has an exam_marks record with score greater than zero for this class.',
                    422
                );
            }

            $publishedAt = now();

            $submissions = $completedTerms
                ->map(function (string $term) use ($classroom, $publishedAt, $user, $validated) {
                    return FinalResultSubmission::updateOrCreate(
                        [
                            'institution_id' => $user->institution_id,
                            'classroom_id' => $classroom->id,
                            'term' => $term,
                        ],
                        [
                            'title' => $validated['title'] ?? 'Final Result ' . ucfirst($term) . ' Term',
                            'is_active' => true,
                            'published_at' => $publishedAt,
                            'published_by' => $user->id,
                        ]
                    );
                })
                ->load(['classroom', 'publisher'])
                ->map(function (FinalResultSubmission $submission) {
                    return [
                        'id' => $submission->id,
                        'title' => $submission->title,
                        'term' => $submission->term->value,
                        'classroom_id' => $submission->classroom_id,
                        'classroom_name' => $submission->classroom?->name,
                        'published_at' => $submission->published_at,
                        'published_by' => $submission->publisher?->full_name,
                        'is_active' => $submission->is_active,
                    ];
                })
                ->values();

            return $this->successResponse($submissions, 'Final results submitted successfully for all completed terms.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
