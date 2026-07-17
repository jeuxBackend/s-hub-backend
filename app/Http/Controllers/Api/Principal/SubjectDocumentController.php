<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectDocumentResource;
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
            $principal = auth()->user();
            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
                'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
                'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
                'document_type' => ['nullable', 'in:yearly_syllabus,study_material'],
            ]);

            $documents = SubjectDocument::query()
                ->where('institution_id', $principal->institution_id)
                ->with(['classroom', 'subject', 'teacher'])
                ->when(isset($validated['teacher_id']), fn ($query) => $query->where('teacher_id', $validated['teacher_id']))
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
        $principal = auth()->user();

        if ($subjectDocument->institution_id !== $principal->institution_id) {
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
