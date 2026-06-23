<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicDocumentResource;
use App\Models\AcademicDocument;
use App\Models\Classroom;
use Throwable;

class AcademicDocumentController extends Controller
{
    public function index()
    {
        try {
            $teacher = auth()->user();

            $classroomIds = Classroom::query()
                ->where('institution_id', $teacher->institution_id)
                ->where(function ($query) use ($teacher) {
                    $query->where('in_charge_id', $teacher->id)
                        ->orWhereHas('subjects', function ($subjectQuery) use ($teacher) {
                            $subjectQuery->where('teacher_id', $teacher->id);
                        })
                        ->orWhereHas('teachers', function ($teacherQuery) use ($teacher) {
                            $teacherQuery->where('teacher_id', $teacher->id);
                        });
                })
                ->pluck('id')
                ->unique()
                ->values();

            $documents = AcademicDocument::query()
                ->where('institution_id', $teacher->institution_id)
                ->whereIn('document_type', ['exam_schedule', 'test_schedule'])
                ->whereIn('classroom_id', $classroomIds)
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
