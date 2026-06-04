<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Models\Classroom;
use App\Http\Resources\SubjectResource;
use Illuminate\Http\Request;
use Throwable;

class PrincipalTimetableController extends Controller
{
    /**
     * Get timetable for a specific teacher.
     */
    public function getTeacherTimetable($teacherId)
    {
        try {
            $institutionId = auth()->user()->institution_id;

            // Verify teacher belongs to the same institution
            $teacher = User::where('id', $teacherId)
                ->where('institution_id', $institutionId)
                ->whereIn('role', [\App\Enums\UserRole::Teacher->value, \App\Enums\UserRole::SchoolAdmin->value])
                ->first();

            if (!$teacher) {
                return $this->errorResponse('Teacher not found in your institution.', 404);
            }

            $subjects = Subject::where('teacher_id', $teacher->id)
                ->with([
                    'classroom' => function ($q) {
                        $q->select('id', 'name', 'code')->withCount('students');
                    }
                ])
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->get()
                ->sortBy(function ($subject) {
                    return strtotime($subject->start_time);
                })
                ->values();

            return $this->successResponse(
                SubjectResource::collection($subjects),
                'Teacher timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get timetable for a specific classroom.
     */
    public function getClassroomTimetable($classroomId)
    {
        try {
            $institutionId = auth()->user()->institution_id;

            // Verify classroom belongs to the same institution
            $classroom = Classroom::where('id', $classroomId)
                ->where('institution_id', $institutionId)
                ->first();

            if (!$classroom) {
                return $this->errorResponse('Classroom not found in your institution.', 404);
            }

            $subjects = Subject::where('classroom_id', $classroom->id)
                ->with(['teacher'])
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->get()
                ->sortBy(function ($subject) {
                    return strtotime($subject->start_time);
                })
                ->values();

            // Append total_students to classroom manually for resource
            $classroom->total_students = $classroom->students()->count();

            // Set the classroom explicitly to prevent N+1 and reuse the data
            $subjects->each(function ($subject) use ($classroom) {
                $subject->setRelation('classroom', $classroom);
            });

            return $this->successResponse(
                SubjectResource::collection($subjects),
                'Classroom timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
