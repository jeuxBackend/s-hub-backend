<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Models\Classroom;
use App\Http\Resources\TimetableEntryResource;
use App\Support\TimetableEntryResolver;
use Throwable;

class PrincipalTimetableController extends Controller
{
    /**
     * Get grouped timetable for the whole institution.
     */
    public function getSchoolTimetable(TimetableEntryResolver $resolver)
    {
        try {
            $institutionId = auth()->user()->institution_id;

            $entries = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->with([
                    'subject',
                    'teacher',
                    'classroom' => function ($query) {
                        $query->select('id', 'name', 'code');
                    },
                ])
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->orderBy('classroom_id')
                ->get();

            $resource = TimetableEntryResource::collection($entries);

            return $this->successResponse(
                $this->groupEntries($resource->resolve(), $resolver),
                'School timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

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

            $entries = TimetableEntry::query()
                ->where('teacher_id', $teacher->id)
                ->with([
                    'subject',
                    'teacher',
                    'classroom' => function ($q) {
                        $q->select('id', 'name', 'code')->withCount('students');
                    },
                ])
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get();

            return $this->successResponse(
                TimetableEntryResource::collection($entries),
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

            $entries = TimetableEntry::query()
                ->where('classroom_id', $classroom->id)
                ->with(['subject', 'teacher', 'classroom'])
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get();

            return $this->successResponse(
                TimetableEntryResource::collection($entries),
                'Classroom timetable retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function groupEntries(array $entries, TimetableEntryResolver $resolver): array
    {
        return collect($entries)
            ->groupBy('weekday')
            ->map(function ($dayEntries, $weekday) use ($resolver) {
                return [
                    'weekday' => (int) $weekday,
                    'weekday_name' => $resolver->weekdayName((int) $weekday),
                    'entries' => array_values($dayEntries->all()),
                ];
            })
            ->values()
            ->all();
    }
}
