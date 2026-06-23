<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicDocumentResource;
use App\Models\AcademicDocument;
use App\Models\Student;
use Throwable;

class AcademicDocumentController extends Controller
{
    public function index()
    {
        try {
            $parent = auth()->user();

            $studentIds = Student::query()
                ->where('guardian_id', $parent->id)
                ->pluck('id')
                ->values();

            $classroomIds = Student::query()
                ->where('guardian_id', $parent->id)
                ->pluck('classroom_id')
                ->filter()
                ->unique()
                ->values();

            $documents = AcademicDocument::query()
                ->where('institution_id', $parent->institution_id)
                ->where(function ($query) use ($studentIds, $classroomIds) {
                    $query->where(function ($classQuery) use ($classroomIds) {
                        $classQuery->whereIn('classroom_id', $classroomIds)
                            ->whereIn('document_type', ['exam_schedule', 'test_schedule']);
                    })->orWhere(function ($studentQuery) use ($studentIds) {
                        $studentQuery->whereIn('student_id', $studentIds)
                            ->where('document_type', 'academic_transcript');
                    });
                })
                ->latest()
                ->get();

            return $this->successResponse(
                AcademicDocumentResource::collection($documents),
                'Academic documents retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
