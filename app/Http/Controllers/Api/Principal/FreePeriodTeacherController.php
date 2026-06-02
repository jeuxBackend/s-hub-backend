<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Teacher\FindFreeTeachersAction;
use App\Actions\Teacher\NotifyFreeTeachersAction;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class FreePeriodTeacherController extends Controller
{
    /**
     * Send notification to a specific teacher for extra class assignment
     * Only if teacher is free during lecture time and same institution
     */
    public function notifyTeacher(
        Request $request,
        FindFreeTeachersAction $findFreeAction,
        NotifyFreeTeachersAction $notifyAction
    ) {
        $request->validate([
            'lecture_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $requester = auth()->user();
            $institutionId = $requester->institution_id;

            // Verify lecture belongs to the same institution
            $lecture = Subject::where('id', $request->lecture_id)
                ->where('institution_id', $institutionId)
                ->first();

            if (!$lecture) {
                return $this->errorResponse('Lecture not found in your institution.', 404);
            }

            if (!$lecture->start_time || !$lecture->end_time) {
                return $this->errorResponse('Lecture does not have a scheduled time.', 400);
            }

            // Verify teacher exists and belongs to the same institution
            $teacher = User::where('id', $request->teacher_id)
                ->where('institution_id', $institutionId)
                ->whereIn('role', ['teacher', 'school-admin'])
                ->first();

            if (!$teacher) {
                return $this->errorResponse('Teacher not found in your institution.', 404);
            }

            // Check if teacher is available (free during lecture time)
            $freeTeachers = $findFreeAction->handle($request->lecture_id, $institutionId);

            if (!in_array($request->teacher_id, $freeTeachers)) {
                return $this->errorResponse(
                    'Teacher is not available during this lecture time.',
                    400
                );
            }

            // Send notification and mark attendance
            $notificationResult = $notifyAction->handle(
                $request->lecture_id,
                [$request->teacher_id],
                $request->message
            );

            return $this->successResponse(
                [
                    'lecture_id' => $lecture->id,
                    'lecture_name' => $lecture->name,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->first_name . ' ' . $teacher->sur_name,
                    'notified' => $notificationResult['notified'],
                    'failed' => $notificationResult['failed'],
                    'status' => $notificationResult['notified'] > 0 ? 'success' : 'failed',
                    'result' => $notificationResult['results'][0] ?? null,
                ],
                $notificationResult['notified'] > 0 ? 'Teacher notified successfully' : 'Failed to notify teacher'
            );

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get free teachers and send notifications for extra class assignment
     */
    public function notifyFreeTeachers(
        Request $request,
        FindFreeTeachersAction $findFreeAction,
        NotifyFreeTeachersAction $notifyAction
    ) {
        $request->validate([
            'lecture_id' => 'required|exists:subjects,id',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $requester = auth()->user();
            $institutionId = $requester->institution_id;

            // Verify lecture belongs to the same institution
            $lecture = Subject::where('id', $request->lecture_id)
                ->where('institution_id', $institutionId)
                ->first();

            if (!$lecture) {
                return $this->errorResponse('Lecture not found in your institution.', 404);
            }

            if (!$lecture->start_time || !$lecture->end_time) {
                return $this->errorResponse('Lecture does not have a scheduled time.', 400);
            }

            // Find free teachers
            $freeTeachers = $findFreeAction->handle($request->lecture_id, $institutionId);

            if (empty($freeTeachers)) {
                return $this->successResponse(
                    [
                        'lecture_id' => $lecture->id,
                        'lecture_name' => $lecture->name,
                        'free_teachers' => [],
                        'notified' => 0,
                        'message' => 'No free teachers available during this lecture time.',
                    ],
                    'No free teachers found'
                );
            }

            // Send notifications and mark attendance
            $notificationResult = $notifyAction->handle(
                $request->lecture_id,
                $freeTeachers,
                $request->message
            );

            return $this->successResponse(
                [
                    'lecture_id' => $lecture->id,
                    'lecture_name' => $lecture->name,
                    'free_teachers_count' => count($freeTeachers),
                    'notified' => $notificationResult['notified'],
                    'failed' => $notificationResult['failed'],
                    'results' => $notificationResult['results'],
                ],
                'Free teachers notified successfully'
            );

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
