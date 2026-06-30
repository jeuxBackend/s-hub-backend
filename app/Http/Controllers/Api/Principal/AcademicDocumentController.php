<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassAcademicDocumentResource;
use App\Http\Resources\AcademicDocumentResource;
use App\Models\AcademicDocument;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AcademicDocumentController extends Controller
{
    private const CLASSROOM_DOCUMENT_TYPES = ['exam_schedule', 'test_schedule'];
    private const TRANSCRIPT_DOCUMENT_TYPE = 'academic_transcript';

    public function storeExamSchedule(Request $request, Classroom $classroom)
    {
        return $this->storeClassDocument($request, $classroom, 'exam_schedule', 'Exam Schedule');
    }

    public function storeTestSchedule(Request $request, Classroom $classroom)
    {
        return $this->storeClassDocument($request, $classroom, 'test_schedule', 'Test Schedule');
    }

    public function storeTranscript(Request $request, Student $student)
    {
        try {
            $validated = $this->validateDocumentPayload($request);

            $principal = auth()->user();

            if ($student->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this student.', 403);
            }

            $path = $validated['file']->store('academic_documents', 'public');

            $document = AcademicDocument::create([
                'institution_id' => $principal->institution_id,
                'student_id' => $student->id,
                'document_type' => 'academic_transcript',
                'title' => $validated['title'] ?? "{$student->full_name} Transcript",
                'file_path' => $path,
                'file_original_name' => $validated['file']->getClientOriginalName(),
                'published_by' => $principal->id,
            ]);

            $document->load(['student.guardian', 'student.classroom', 'classroom', 'publisher']);

            return $this->successResponse(
                new AcademicDocumentResource($document),
                'Academic transcript uploaded successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, AcademicDocument $academicDocument)
    {
        try {
            $validated = $this->validateDocumentPayload($request, false);

            if (! $request->hasFile('file') && ! $request->filled('title')) {
                return $this->errorResponse('At least one field must be provided to update the document.', 422);
            }

            $principal = auth()->user();

            if ($academicDocument->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this academic document.', 403);
            }

            if ($request->hasFile('file')) {
                $newPath = $validated['file']->store('academic_documents', 'public');

                if ($academicDocument->file_path && Storage::disk('public')->exists($academicDocument->file_path)) {
                    Storage::disk('public')->delete($academicDocument->file_path);
                }

                $academicDocument->file_path = $newPath;
                $academicDocument->file_original_name = $validated['file']->getClientOriginalName();
            }

            if ($request->filled('title')) {
                $academicDocument->title = $validated['title'];
            }

            $academicDocument->save();
            $academicDocument->load(['student.guardian', 'student.classroom', 'classroom', 'publisher']);

            return $this->successResponse(
                $this->transformDocumentResource($academicDocument),
                'Academic document updated successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function storeClassDocument(Request $request, Classroom $classroom, string $type, string $defaultTitle)
    {
        try {
            $validated = $this->validateDocumentPayload($request);

            $principal = auth()->user();

            if ($classroom->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this classroom.', 403);
            }

            $path = $validated['file']->store('academic_documents', 'public');

            $document = AcademicDocument::create([
                'institution_id' => $principal->institution_id,
                'classroom_id' => $classroom->id,
                'document_type' => $type,
                'title' => $validated['title'] ?? "{$classroom->name} {$defaultTitle}",
                'file_path' => $path,
                'file_original_name' => $validated['file']->getClientOriginalName(),
                'published_by' => $principal->id,
            ]);

            $document->load(['classroom', 'publisher']);

            return $this->successResponse(
                $this->transformDocumentResource($document),
                ucfirst(str_replace('_', ' ', $type)) . ' uploaded successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getClassroomAcademicDocuments(Request $request, Classroom $classroom)
    {
        try {
            $principal = auth()->user();

            if ($classroom->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this classroom.', 403);
            }

            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $documents = AcademicDocument::query()
                ->where('institution_id', $principal->institution_id)
                ->where('classroom_id', $classroom->id)
                ->whereIn('document_type', self::CLASSROOM_DOCUMENT_TYPES)
                ->with(['classroom', 'publisher'])
                ->latest()
                ->paginate($validated['per_page'] ?? 15);

            return $this->paginatedResponse(
                ClassAcademicDocumentResource::collection($documents),
                'Academic documents fetched successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getClassroomTranscripts(Request $request, Classroom $classroom)
    {
        try {
            $principal = auth()->user();

            if ($classroom->institution_id !== $principal->institution_id) {
                return $this->errorResponse('Unauthorized access to this classroom.', 403);
            }

            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $studentIds = Student::query()
                ->where('institution_id', $principal->institution_id)
                ->where('classroom_id', $classroom->id)
                ->pluck('id')
                ->values();

            $documents = AcademicDocument::query()
                ->where('institution_id', $principal->institution_id)
                ->whereIn('student_id', $studentIds)
                ->where('document_type', self::TRANSCRIPT_DOCUMENT_TYPE)
                ->with(['student.guardian', 'student.classroom', 'classroom', 'publisher'])
                ->latest()
                ->paginate($validated['per_page'] ?? 15);

            return $this->paginatedResponse(
                AcademicDocumentResource::collection($documents),
                'Academic transcripts fetched successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function validateDocumentPayload(Request $request, bool $requireFile = true): array
    {
        return $request->validate([
            'file' => [$requireFile ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function transformDocumentResource(AcademicDocument $document)
    {
        if ($document->student_id !== null) {
            return new AcademicDocumentResource($document);
        }

        return new ClassAcademicDocumentResource($document);
    }
}
