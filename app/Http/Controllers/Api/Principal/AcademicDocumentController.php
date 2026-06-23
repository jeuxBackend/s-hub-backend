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
            $validated = $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

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

            $document->load(['student', 'classroom', 'publisher']);

            return $this->successResponse(
                new AcademicDocumentResource($document),
                'Academic transcript uploaded successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function storeClassDocument(Request $request, Classroom $classroom, string $type, string $defaultTitle)
    {
        try {
            $validated = $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

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
                new ClassAcademicDocumentResource($document),
                ucfirst(str_replace('_', ' ', $type)) . ' uploaded successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
