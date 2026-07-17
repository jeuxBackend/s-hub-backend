<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeacherAttendance;
use App\Models\Subject;
use App\Models\NotificationLog;
use App\Models\TimetableEntry;
use App\Support\TimetableEntryResolver;
use Carbon\Carbon;
use Throwable;

class TeacherAttendanceController extends Controller
{
    /**
     * Mark teacher attendance
     */
    public function markAttendance(Request $request, TimetableEntryResolver $resolver)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'message' => 'nullable|string|max:1000',
        ]);

        try {
            $teacher = auth()->user();
            $subject = Subject::where('id', $request->subject_id)
                ->with('institution')
                ->where('institution_id', $teacher->institution_id)
                ->first();

            if (!$subject) {
                return $this->errorResponse('Subject not found in your institution.', 404);
            }

            $teacherTimezone = $teacher->timezone ?? 'UTC';
            $now = Carbon::now($teacherTimezone);
            $entry = TimetableEntry::query()
                ->where('institution_id', $teacher->institution_id)
                ->where('subject_id', $subject->id)
                ->where('teacher_id', $teacher->id)
                ->where('weekday', $now->isoWeekday())
                ->orderBy('start_time')
                ->first();

            if (!$entry) {
                return $this->errorResponse('This subject is not assigned to you in today\'s timetable.', 400);
            }

            [$startTime, $endTime] = $resolver->buildDateTimeRange($entry, $now, $teacherTimezone);

            $bufferMinutes = 30;
            if ($now->lessThan($startTime->copy()->subMinutes($bufferMinutes)) || $now->greaterThan($endTime->copy()->addMinutes($bufferMinutes))) {
                return $this->errorResponse('You can only mark attendance during the scheduled time for this class.', 400);
            }

            $attendanceDate = $now->toDateString();
            $attendanceRequestKey = NotificationLog::attendanceRequestKey(
                (int) $subject->id,
                $attendanceDate,
                (int) $teacher->id
            );

            $isRemote = $teacher->remote_teaching;

            if (!$isRemote) {
                if (!$request->filled('latitude') || !$request->filled('longitude')) {
                    return $this->errorResponse('Latitude and longitude are required to mark attendance.', 400);
                }

                $institution = $subject->institution;
                if (!$institution || !$institution->latitude || !$institution->longitude) {
                    return $this->errorResponse('Institution location is not set. Please contact admin.', 400);
                }

                $distance = $this->calculateDistance(
                    $request->latitude,
                    $request->longitude,
                    $institution->latitude,
                    $institution->longitude
                );

                if ($distance > 300) {
                    return $this->errorResponse('You are too far from the institution to mark attendance. You must be within 300 meters.', 403);
                }
            }

            $existingAttendance = TeacherAttendance::where('teacher_id', $teacher->id)
                ->where('subject_id', $subject->id)
                ->whereDate('date', $attendanceDate)
                ->first();

            $proxyAttendanceExists = TeacherAttendance::where('subject_id', $subject->id)
                ->whereDate('date', $attendanceDate)
                ->where('status', 'proxy')
                ->exists();

            if ($proxyAttendanceExists) {
                NotificationLog::expireAttendanceRequest($attendanceRequestKey);
                return $this->errorResponse('Attendance for this class has already been completed by a proxy teacher.', 409);
            }

            if ($existingAttendance) {
                NotificationLog::expireAttendanceRequest($attendanceRequestKey);
                return $this->errorResponse('Attendance has already been recorded for this class.', 409);
            }

            if ($existingAttendance) {
                $existingAttendance->update([
                    'institution_id' => $subject->institution_id,
                    'status' => 'present',
                    'type' => 'regular',
                    'is_remote' => $isRemote,
                    'message' => $request->input('message'),
                ]);

                $attendance = $existingAttendance;
            } else {
                $attendance = TeacherAttendance::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'institution_id' => $subject->institution_id,
                    'date' => $attendanceDate,
                    'status' => 'present',
                    'type' => 'regular',
                    'is_remote' => $isRemote,
                    'message' => $request->input('message'),
                ]);
            }

            NotificationLog::expireAttendanceRequest($attendanceRequestKey);

            if ($subject->is_proxy && $subject->proxy_teacher_id) {
                $subject->update([
                    'is_proxy' => false,
                    'proxy_teacher_id' => null,
                    'proxy_start_time' => null,
                    'proxy_end_time' => null,
                ]);
            }

            return $this->successResponse($attendance, 'Attendance marked successfully.');

        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Calculate distance between two coordinates in meters using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance;
    }
}
