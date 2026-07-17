<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectDocumentResource;
use App\Models\Student;
use App\Models\SubjectDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SubjectDocumentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $parent = auth()->user();
            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'student_id' => ['nullable', 'integer', 'exists:students,id'],
                'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
                'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
                'document_type' => ['nullable', 'in:yearly_syllabus,study_material'],
            ]);

            $studentQuery = Student::query()
                ->where('guardian_id', $parent->id)
                ->where('institution_id', $parent->institution_id);

            if (isset($validated['student_id'])) {
                $studentQuery->where('id', $validated['student_id']);
            }

            $students = $studentQuery->get(['id', 'classroom_id']);

            if ($students->isEmpty()) {
                return $this->paginatedResponse(
                    SubjectDocumentResource::collection(SubjectDocument::query()->whereRaw('1 = 0')->paginate($validated['per_page'] ?? 15)),
                    'Subject documents retrieved successfully.'
                );
            }

            $classroomIds = $students->pluck('classroom_id')->filter()->unique()->values();

            $documents = SubjectDocument::query()
                ->where('institution_id', $parent->institution_id)
                ->whereIn('classroom_id', $classroomIds)
                ->with(['classroom', 'subject', 'teacher'])
                ->when(isset($validated['classroom_id']), fn ($query) => $query->where('classroom_id', $validated['classroom_id']))
                ->when(isset($validated['subject_id']), fn ($query) => $query->where('subject_id', $validated['subject_id']))
                ->when(isset($validated['document_type']), fn ($query) => $query->where('document_type', $validated['document_type']))
                ->latest()
                ->paginate($validated['per_page'] ?? 15);

            return $this->paginatedResponse(
                SubjectDocumentResource::collection($documents),
                'Subject documents retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function download(SubjectDocument $subjectDocument): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $parent = auth()->user();

        $hasChildInClassroom = Student::query()
            ->where('guardian_id', $parent->id)
            ->where('institution_id', $parent->institution_id)
            ->where('classroom_id', $subjectDocument->classroom_id)
            ->exists();

        if (!$hasChildInClassroom || $subjectDocument->institution_id !== $parent->institution_id) {
            return $this->errorResponse('Unauthorized access to this subject document.', 403);
        }

        if (!Storage::disk('public')->exists($subjectDocument->file_path)) {
            return $this->errorResponse('The requested file could not be found.', 404);
        }

        return Storage::disk('public')->download(
            $subjectDocument->file_path,
            $subjectDocument->file_original_name ?? basename($subjectDocument->file_path)
        );
    }
}
