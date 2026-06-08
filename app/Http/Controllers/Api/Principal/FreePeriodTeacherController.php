<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Teacher\FindFreeTeachersAction;
use App\Actions\Teacher\NotifyFreeTeachersAction;
use App\Models\Subject;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;
use Carbon\Carbon;

class FreePeriodTeacherController extends Controller
{
    /**
     * Send notification to a specific teacher for extra class assignment
     * Only if teacher is free during lecture time and same institution
     */
    public function notifyTeacher(
        Request $request,
        FindFreeTeachersAction $findFreeAction,
        // NotifyFreeTeachersAction $notifyAction
    ) {
        $request->validate([
            'lecture_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:users,id',
            // 'message' => 'nullable|string|max:500',
        ]);



        try {
            $requester = auth()->user();
            $institutionId = $requester->institution_id;

            // Verify lecture belongs to the same institution
            $lecture = Subject::where('id', $request->lecture_id)
                ->where('institution_id', $institutionId)->with('classroom')
                ->first();



            if (!empty($lecture->classroom->in_charge_id)) {
                $request->merge([
                    'message' => "You have assigned {$lecture->name} in {$lecture->classroom->name} and marks student attendance also."
                ]);
            } else {
                $request->merge([
                    'message' => "You have assigned {$lecture->name} in {$lecture->classroom->name}."
                ]);
            }

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

            // Convert time strings to proper datetime format for today
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $proxyStartTime = \Carbon\Carbon::parse($lecture->start_time);
            $proxyEndTime = \Carbon\Carbon::parse($lecture->end_time);
            $attendanceRequestKey = NotificationLog::attendanceRequestKey(
                (int) $lecture->id,
                $today,
                (int) $lecture->teacher_id
            );

            if (NotificationLog::attendanceRequestCompleted((int) $lecture->id, $today, (int) $lecture->teacher_id)) {
                return $this->errorResponse('Attendance has already been completed for this lecture.', 409);
            }

            $lecture->update([
                'is_proxy' => true,
                'proxy_teacher_id' => $teacher->id,
                'proxy_start_time' => $proxyStartTime ? $today . ' ' . $proxyStartTime->format('H:i:s') : null,
                'proxy_end_time' => $proxyEndTime ? $today . ' ' . $proxyEndTime->format('H:i:s') : null,
            ]);

            // Create in-app notification for the proxy teacher
            $notificationTitle = 'Proxy Class Assignment';
            $notificationMessage = $request->message ?? "You have been assigned as a proxy teacher for {$lecture->name} from {$lecture->start_time} to {$lecture->end_time}.";
            $proxyNotification = \App\Models\NotificationLog::create([
                'user_id' => $teacher->id,
                'type' => 'proxy_class_assignment',
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'is_read' => false,
                'attendance_request_key' => $attendanceRequestKey,
                'meta' => [
                    'subject_id' => (string) $lecture->id,
                    'subject_name' => $lecture->name,
                    'classroom_id' => (string) $lecture->classroom_id,
                    'classroom_name' => $lecture->classroom?->name ?? 'N/A',
                    'start_time' => Carbon::parse($lecture->start_time)->format('g:i a'),
                    'end_time' => Carbon::parse($lecture->end_time)->format('g:i a'),
                    'proxy_start_time' => $proxyStartTime ? $proxyStartTime->format('g:i A') : null,
                    'proxy_end_time' => $proxyEndTime ? $proxyEndTime->format('g:i A') : null,
                    'original_teacher_id' => (string) $lecture->teacher_id,
                    'original_teacher_name' => $lecture->teacher?->full_name ?? 'N/A',
                    'attendance_request_key' => $attendanceRequestKey,
                ],
                'sent_at' => now(),
            ]);

            // Send Firebase push notification if teacher has FCM token
            if ($teacher->fcm_token) {
                try {
                    \Illuminate\Support\Facades\Log::info('Attempting to send push notification to proxy teacher', [
                        'teacher_id' => $teacher->id,
                        'teacher_name' => $teacher->full_name,
                        'fcm_token_length' => strlen($teacher->fcm_token),
                    ]);

                    // Check if Firebase credentials exist
                    $credentialsPath = env('FIREBASE_CREDENTIALS', storage_path('app/firebase/firebase-creds.json'));
                    if (!file_exists($credentialsPath)) {
                        \Illuminate\Support\Facades\Log::error('Firebase credentials file not found', [
                            'path' => $credentialsPath,
                            'teacher_id' => $teacher->id,
                        ]);
                    } else {
                        \Illuminate\Support\Facades\Log::info('Firebase credentials file found', ['path' => $credentialsPath]);
                    }

                    $firebaseService = app(\App\Services\FirebaseNotificationService::class);
                    $result = $firebaseService->sendToToken(
                        $teacher->fcm_token,
                        $notificationTitle,
                        $notificationMessage,
                        [
                            'type' => 'proxy_class_assignment',
                            'subject_id' => (string) $lecture->id,
                            'subject_name' => $lecture->name,
                            'classroom_name' => $lecture->classroom?->name ?? 'N/A',
                        ]
                    );

                    \Illuminate\Support\Facades\Log::info('Push notification result', [
                        'result' => $result,
                    ]);

                    if ($result) {
                        \Illuminate\Support\Facades\Log::info('Push notification sent successfully to proxy teacher', [
                            'teacher_id' => $teacher->id,
                            'message_id' => $result,
                        ]);
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Push notification returned false for proxy teacher. Check Firebase configuration.', [
                            'teacher_id' => $teacher->id,
                            'credentials_exist' => file_exists($credentialsPath),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send push notification to proxy teacher: ' . $e->getMessage(), [
                        'teacher_id' => $teacher->id,
                        'error_class' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('Teacher has no FCM token, skipping push notification', [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->full_name,
                ]);
            }

            // //Send notification and mark attendance
            // $notificationResult = $notifyAction->handle(
            //     $request->lecture_id,
            //     [$request->teacher_id],
            //     $request->message
            // );

            return $this->successResponse(
                [
                    'lecture_id' => $lecture->id,
                    'lecture_name' => $lecture->name,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->first_name . ' ' . $teacher->sur_name,
                    // 'notified' => $notificationResult['notified'],
                    // 'failed' => $notificationResult['failed'],
                    // 'status' => $notificationResult['notified'] > 0 ? 'success' : 'failed',
                    // 'result' => $notificationResult['results'][0] ?? null,
                ],
                // $notificationResult['notified'] > 0 ? 'Teacher notified successfully' : 'Failed to notify teacher'
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

    /**
     * Mark proxy attendance for a subject that has proxy settings enabled
     * Only works for subjects where is_proxy = true and proxy_teacher_id is set
     * Accessible by the proxy teacher themselves
     * Creates two attendance records: actual teacher (absent) and proxy teacher (proxy)
     */
    public function markProxyAttendance(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'nullable|date',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'notification_id' => 'required|exists:notification_logs,id'
        ]);

        try {
            $proxyTeacher = auth()->user();
            $institutionId = $proxyTeacher->institution_id;

            // Find the subject with proxy settings
            $subject = Subject::where('id', $request->subject_id)
                ->where('institution_id', $institutionId)
                ->where('is_proxy', true)
                ->whereNotNull('proxy_teacher_id')
                ->first();

            if (!$subject) {
                return $this->errorResponse(
                    'Subject not found or does not have proxy settings enabled.',
                    404
                );
            }

            // Verify that the authenticated user is the proxy teacher for this subject
            if ($subject->proxy_teacher_id != $proxyTeacher->id) {
                return $this->errorResponse(
                    'You are not assigned as the proxy teacher for this subject.',
                    403
                );
            }

            $date = $request->filled('date')
                ? \Carbon\Carbon::parse($request->date)->toDateString()
                : \Carbon\Carbon::today()->toDateString();

            $notification = NotificationLog::where('id', $request->notification_id)
                ->where('user_id', $proxyTeacher->id)
                ->first();

            $attendanceRequestKey = $notification?->attendance_request_key
                ?? NotificationLog::attendanceRequestKey(
                    (int) $subject->id,
                    $date,
                    (int) $subject->teacher_id
                );

            if ($notification && empty($notification->attendance_request_key)) {
                $notification->update(['attendance_request_key' => $attendanceRequestKey]);
            }

            // Check if proxy has expired (only 15 minutes left in proxy_end_time or time has passed)
            if ($subject->proxy_end_time) {
                $proxyEndTime = \Carbon\Carbon::parse($subject->proxy_end_time);
                $currentTime = \Carbon\Carbon::now();

                // Calculate minutes remaining until end time
                $minutesRemaining = $currentTime->diffInMinutes($proxyEndTime, false);

                // If time has passed (negative) or 15 minutes or less remaining, mark as expired
                if ($minutesRemaining <= 15) {
                    return $this->errorResponse(
                        'Proxy session has expired. Only 15 minutes or less remaining.',
                        403
                    );
                }
            }

            // Verify teacher's location is within 300 meters of institution
            $institution = \App\Models\Institution::find($institutionId);

            if (!$institution || !$institution->latitude || !$institution->longitude) {
                return $this->errorResponse(
                    'Institution location not configured. Please contact administrator.',
                    500
                );
            }

            $teacherLat = $request->input('latitude');
            $teacherLng = $request->input('longitude');
            $institutionLat = $institution->latitude;
            $institutionLng = $institution->longitude;

            // Calculate distance using Haversine formula
            $distance = $this->calculateDistance($teacherLat, $teacherLng, $institutionLat, $institutionLng);

            // Check if teacher is within 300 meters radius
            if ($distance > 300) {
                \Illuminate\Support\Facades\Log::warning('Proxy teacher outside allowed radius', [
                    'teacher_id' => $proxyTeacher->id,
                    'teacher_name' => $proxyTeacher->full_name,
                    'distance_meters' => round($distance, 2),
                    'teacher_location' => ['lat' => $teacherLat, 'lng' => $teacherLng],
                    'institution_location' => ['lat' => $institutionLat, 'lng' => $institutionLng],
                ]);

                return $this->errorResponse(
                    'You must be within 300 meters of the institution to mark attendance. Current distance: ' . round($distance) . ' meters.',
                    403
                );
            }

            \Illuminate\Support\Facades\Log::info('Proxy teacher location verified', [
                'teacher_id' => $proxyTeacher->id,
                'distance_meters' => round($distance, 2),
            ]);

            // Check if proxy attendance already exists FIRST (before creating any records)
            $existingProxyAttendance = \App\Models\TeacherAttendance::where('teacher_id', $proxyTeacher->id)
                ->where('subject_id', $subject->id)
                ->whereDate('date', $date)
                ->first();

            if ($existingProxyAttendance) {
                // Return the existing record instead of error
                $actualTeacher = User::find($subject->teacher_id);
                $actualTeacherAttendance = \App\Models\TeacherAttendance::where('teacher_id', $actualTeacher->id)
                    ->where('subject_id', $subject->id)
                    ->whereDate('date', $date)
                    ->first();

                // Clear proxy-related data from subjects table (in case it wasn't cleared before)
                if ($subject->is_proxy) {
                    $subject->update([
                        'is_proxy' => false,
                        'proxy_teacher_id' => null,
                        'proxy_start_time' => null,
                        'proxy_end_time' => null,
                    ]);

                    \Illuminate\Support\Facades\Log::info('Proxy data cleared from subject on duplicate request', [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                    ]);
                }

                NotificationLog::expireAttendanceRequest($attendanceRequestKey);

                return $this->successResponse(
                    [
                        'actual_teacher_attendance' => $actualTeacherAttendance ? [
                            'attendance_id' => $actualTeacherAttendance->id,
                            'teacher_id' => $actualTeacherAttendance->teacher_id,
                            'teacher_name' => $actualTeacher->first_name . ' ' . $actualTeacher->sur_name,
                            'status' => $actualTeacherAttendance->status,
                            'type' => $actualTeacherAttendance->type,
                        ] : null,
                        'proxy_teacher_attendance' => [
                            'attendance_id' => $existingProxyAttendance->id,
                            'teacher_id' => $proxyTeacher->id,
                            'teacher_name' => $proxyTeacher->first_name . ' ' . $proxyTeacher->sur_name,
                            'subject_id' => $subject->id,
                            'subject_name' => $subject->name,
                            'date' => $existingProxyAttendance->date,
                            'status' => 'present',
                            'type' => 'proxy',
                            'is_remote' => false,
                            'verified_location' => true,
                            'distance_from_institution' => round($distance, 2) . ' meters',
                            'already_marked' => true,
                        ],
                    ],
                    'Proxy attendance was already marked for this subject on ' . $date
                );
            }

            // Get the actual teacher
            $actualTeacher = User::find($subject->teacher_id);

            if (!$actualTeacher) {
                return $this->errorResponse('Actual teacher not found.', 404);
            }

            // Check if actual teacher's attendance already exists
            $existingActualAttendance = \App\Models\TeacherAttendance::where('teacher_id', $actualTeacher->id)
                ->where('subject_id', $subject->id)
                ->whereDate('date', $date)
                ->first();

            // If actual teacher attendance exists but is NOT absent (e.g., marked present later), prevent proxy
            if ($existingActualAttendance && $existingActualAttendance->status != 'absent') {
                NotificationLog::expireAttendanceRequest($attendanceRequestKey);
                return $this->errorResponse(
                    'Attendance already exists for the actual teacher on ' . $date . ' with status: ' . $existingActualAttendance->status,
                    409
                );
            }

            // If actual teacher attendance doesn't exist OR is marked as absent (by cron), update/create it
            if ($existingActualAttendance) {
                // Cron already marked as absent, just verify it's still absent
                if ($existingActualAttendance->status != 'absent') {
                    return $this->errorResponse('Cannot mark proxy attendance. Actual teacher attendance status is: ' . $existingActualAttendance->status, 409);
                }
                // Attendance already marked as absent by cron, no need to create again
            } else {
                // Create attendance record for actual teacher (marked as absent)
                \App\Models\TeacherAttendance::create([
                    'teacher_id' => $actualTeacher->id,
                    'subject_id' => $subject->id,
                    'institution_id' => $institutionId,
                    'date' => $date,
                    'status' => 'absent',
                    'type' => 'regular',
                    'is_remote' => true,
                ]);
            }

            // Create proxy attendance record (automatically marked as present)
            $proxyAttendance = \App\Models\TeacherAttendance::create([
                'teacher_id' => $proxyTeacher->id,
                'subject_id' => $subject->id,
                'institution_id' => $institutionId,
                'date' => $date,
                'status' => 'proxy',
                'type' => 'proxy',
                'is_remote' => false,
            ]);

            NotificationLog::expireAttendanceRequest($attendanceRequestKey);

            // Clear proxy-related data from subjects table to prevent reusing this subject for proxy
            $subject->update([
                'is_proxy' => false,
                'proxy_teacher_id' => null,
                'proxy_start_time' => null,
                'proxy_end_time' => null,
            ]);

            \Illuminate\Support\Facades\Log::info('Proxy data cleared from subject after attendance marked', [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
            ]);

            // Get or refresh the actual teacher attendance record for response
            $actualTeacherAttendance = \App\Models\TeacherAttendance::where('teacher_id', $actualTeacher->id)
                ->where('subject_id', $subject->id)
                ->whereDate('date', $date)
                ->first();

            return $this->successResponse(
                [
                    'actual_teacher_attendance' => [
                        'attendance_id' => $actualTeacherAttendance->id,
                        'teacher_id' => $actualTeacher->id,
                        'teacher_name' => $actualTeacher->first_name . ' ' . $actualTeacher->sur_name,
                        'status' => 'absent',
                        'type' => 'regular',
                    ],
                    'proxy_teacher_attendance' => [
                        'attendance_id' => $proxyAttendance->id,
                        'teacher_id' => $proxyTeacher->id,
                        'teacher_name' => $proxyTeacher->first_name . ' ' . $proxyTeacher->sur_name,
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'date' => $proxyAttendance->date,
                        'status' => 'present',
                        'type' => 'proxy',
                        'is_remote' => false,
                        'verified_location' => true,
                        'distance_from_institution' => round($distance, 2) . ' meters',
                    ],
                ],
                'Proxy attendance marked successfully. Location verified.'
            );

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth's radius in meters

        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        // Haversine formula
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
