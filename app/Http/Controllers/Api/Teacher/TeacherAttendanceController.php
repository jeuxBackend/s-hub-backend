<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeacherAttendance;
use App\Models\Subject;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Throwable;

class TeacherAttendanceController extends Controller
{
    /**
     * Mark teacher attendance
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $teacher = auth()->user();
            $subject = Subject::where('id', $request->subject_id)
                ->where('teacher_id', $teacher->id)
                ->with('institution')
                ->first();

            if (!$subject) {
                return $this->errorResponse('Subject not found or you are not assigned to this subject.', 404);
            }

            if (!$subject->start_time || !$subject->end_time) {
                return $this->errorResponse('This subject does not have a scheduled time in the timetable.', 400);
            }

            // Check if current time is within subject time (with some buffer, e.g. 30 mins before or after)
            $now = Carbon::now();
            $startTime = Carbon::parse($subject->start_time);
            $endTime = Carbon::parse($subject->end_time);
            
            // Adjust to today's date for accurate comparison
            $startTime->setDate($now->year, $now->month, $now->day);
            $endTime->setDate($now->year, $now->month, $now->day);

            if ($endTime->lessThan($startTime)) {
                $endTime->addDay(); // Handle overnight classes
            }

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

                // Must check location
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

                // Check if within 300 meters (0.3 km)
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

            if ($existingAttendance && in_array($existingAttendance->status, ['present', 'proxy'], true)) {
                NotificationLog::expireAttendanceRequest($attendanceRequestKey);
                return $this->errorResponse('Attendance has already been marked for this class.', 409);
            }

            if ($existingAttendance) {
                $existingAttendance->update([
                    'institution_id' => $subject->institution_id,
                    'status' => 'present',
                    'type' => 'regular',
                    'is_remote' => $isRemote,
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
        $earthRadius = 6371000; // Radius of the earth in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c; // Distance in meters

        return $distance;
    }
}
