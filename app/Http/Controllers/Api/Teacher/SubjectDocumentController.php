<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectDocument\StoreSubjectDocumentRequest;
use App\Http\Requests\SubjectDocument\UpdateSubjectDocumentRequest;
use App\Http\Resources\SubjectDocumentResource;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectDocument;
use App\Support\TeacherSubjectAssignmentResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SubjectDocumentController extends Controller
{
    public function allowedAssignments(Request $request, TeacherSubjectAssignmentResolver $resolver)
    {
        try {
            $validated = $request->validate([
                'config_id' => ['nullable', 'integer', 'exists:school_timetable_configs,id'],
                'academic_year' => ['nullable', 'string', 'max:100'],
                'term' => ['nullable', 'string', 'max:100'],
            ]);

            $assignments = $resolver->getAllowedAssignments(auth()->user(), $validated);

            $data = $assignments->map(fn ($entry) => [
                'classroom_id' => $entry->classroom_id,
                'subject_id' => $entry->subject_id,
                'classroom' => [
                    'id' => $entry->classroom?->id,
                    'name' => $entry->classroom?->name,
                    'code' => $entry->classroom?->code,
                ],
                'subject' => [
                    'id' => $entry->subject?->id,
                    'name' => $entry->subject?->name,
                    'code' => $entry->subject?->code,
                ],
            ])->values();

            return $this->successResponse($data, 'Allowed class and subject assignments retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function index(Request $request)
    {
        try {
            $teacher = auth()->user();
            $validated = $request->validate([
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'class_id' => ['nullable', 'integer', 'exists:classrooms,id'],
                'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
                'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
                'term' => ['nullable', 'string', 'max:100'],
                'document_type' => ['nullable', 'in:yearly_syllabus,study_material'],
            ]);

            $classroomId = $validated['class_id'] ?? $validated['classroom_id'] ?? null;

            $documents = SubjectDocument::query()
                ->where('institution_id', $teacher->institution_id)
                ->where('teacher_id', $teacher->id)
                ->with(['classroom', 'subject', 'teacher'])
                ->when($classroomId !== null, fn ($query) => $query->where('classroom_id', $classroomId))
                ->when(isset($validated['subject_id']), fn ($query) => $query->where('subject_id', $validated['subject_id']))
                ->when(isset($validated['term']), fn ($query) => $query->where('term', $validated['term']))
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

    public function store(StoreSubjectDocumentRequest $request, TeacherSubjectAssignmentResolver $resolver)
    {
        try {
            $teacher = auth()->user();
            $validated = $request->validated();

            $classroom = Classroom::query()
                ->where('institution_id', $teacher->institution_id)
                ->find($validated['classroom_id']);
            $subject = Subject::query()
                ->where('institution_id', $teacher->institution_id)
                ->where('classroom_id', $validated['classroom_id'])
                ->find($validated['subject_id']);

            if (!$classroom || !$subject) {
                return $this->errorResponse('The selected classroom and subject do not belong to your institution.', 422);
            }

            if (!$resolver->teacherCanManage($teacher, $classroom->id, $subject->id)) {
                return $this->errorResponse('You can upload files only for classes assigned to you through the rota system.', 403);
            }

            $documents = DB::transaction(function () use ($request, $validated, $teacher) {
                if ($validated['document_type'] === 'yearly_syllabus') {
                    $file = $validated['file'];
                    $document = SubjectDocument::query()->firstOrNew([
                        'institution_id' => $teacher->institution_id,
                        'classroom_id' => $validated['classroom_id'],
                        'subject_id' => $validated['subject_id'],
                        'document_type' => 'yearly_syllabus',
                    ]);

                    if ($document->exists && $document->file_path && Storage::disk('public')->exists($document->file_path)) {
                        Storage::disk('public')->delete($document->file_path);
                    }

                    $document->fill([
                        'teacher_id' => $teacher->id,
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'academic_year' => $validated['academic_year'] ?? null,
                        'term' => $validated['term'] ?? null,
                    ]);

                    $this->fillStoredFileMetadata($document, $file);
                    $document->save();

                    return collect([$document]);
                }

                $documents = collect();

                foreach (array_values($validated['materials']) as $index => $material) {
                    $file = $request->file("materials.{$index}.file");
                    $document = new SubjectDocument([
                        'institution_id' => $teacher->institution_id,
                        'classroom_id' => $validated['classroom_id'],
                        'subject_id' => $validated['subject_id'],
                        'teacher_id' => $teacher->id,
                        'document_type' => 'study_material',
                        'title' => $material['title'],
                        'description' => $material['description'] ?? null,
                        'academic_year' => $validated['academic_year'] ?? null,
                        'term' => $validated['term'] ?? null,
                    ]);

                    $this->fillStoredFileMetadata($document, $file);
                    $document->save();
                    $documents->push($document);
                }

                return $documents;
            });

            $documents->each->load(['classroom', 'subject', 'teacher']);

            return $this->successResponse(
                SubjectDocumentResource::collection($documents),
                $validated['document_type'] === 'yearly_syllabus'
                    ? 'Yearly syllabus uploaded successfully.'
                    : 'Study materials uploaded successfully.',
                201
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(
        UpdateSubjectDocumentRequest $request,
        SubjectDocument $subjectDocument,
        TeacherSubjectAssignmentResolver $resolver
    ) {
        try {
            $teacher = auth()->user();

            if (!$this->teacherOwnsDocument($teacher->id, $teacher->institution_id, $subjectDocument)) {
                return $this->errorResponse('Unauthorized access to this subject document.', 403);
            }

            if (!$resolver->teacherCanManage($teacher, $subjectDocument->classroom_id, $subjectDocument->subject_id)) {
                return $this->errorResponse('You can manage files only for classes assigned to you through the rota system.', 403);
            }

            $validated = $request->validated();

            if (empty($validated) && !$request->hasFile('file')) {
                return $this->errorResponse('At least one field must be provided to update the document.', 422);
            }

            if ($request->hasFile('file')) {
                if ($subjectDocument->file_path && Storage::disk('public')->exists($subjectDocument->file_path)) {
                    Storage::disk('public')->delete($subjectDocument->file_path);
                }

                $this->fillStoredFileMetadata($subjectDocument, $request->file('file'));
            }

            $subjectDocument->fill([
                'title' => $validated['title'] ?? $subjectDocument->title,
                'description' => array_key_exists('description', $validated)
                    ? $validated['description']
                    : $subjectDocument->description,
                'academic_year' => $validated['academic_year'] ?? $subjectDocument->academic_year,
                'term' => $validated['term'] ?? $subjectDocument->term,
            ]);

            $subjectDocument->save();
            $subjectDocument->load(['classroom', 'subject', 'teacher']);

            return $this->successResponse(
                new SubjectDocumentResource($subjectDocument),
                'Subject document updated successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(SubjectDocument $subjectDocument, TeacherSubjectAssignmentResolver $resolver)
    {
        try {
            $teacher = auth()->user();

            if (!$this->teacherOwnsDocument($teacher->id, $teacher->institution_id, $subjectDocument)) {
                return $this->errorResponse('Unauthorized access to this subject document.', 403);
            }

            if (!$resolver->teacherCanManage($teacher, $subjectDocument->classroom_id, $subjectDocument->subject_id)) {
                return $this->errorResponse('You can manage files only for classes assigned to you through the rota system.', 403);
            }

            if ($subjectDocument->file_path && Storage::disk('public')->exists($subjectDocument->file_path)) {
                Storage::disk('public')->delete($subjectDocument->file_path);
            }

            $subjectDocument->delete();

            return $this->successResponse(null, 'Subject document deleted successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function download(SubjectDocument $subjectDocument): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $teacher = auth()->user();

        if (!$this->teacherOwnsDocument($teacher->id, $teacher->institution_id, $subjectDocument)) {
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

    private function teacherOwnsDocument(int $teacherId, int $institutionId, SubjectDocument $subjectDocument): bool
    {
        return $subjectDocument->teacher_id === $teacherId
            && $subjectDocument->institution_id === $institutionId;
    }

    private function fillStoredFileMetadata(SubjectDocument $document, $file): void
    {
        $document->file_path = $file->store('teacher_uploads/subject_documents', 'public');
        $document->file_original_name = $file->getClientOriginalName();
        $document->mime_type = $file->getClientMimeType();
        $document->file_size = $file->getSize();
    }
}
