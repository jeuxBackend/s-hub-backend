<?php

namespace App\Actions\Attendance;

use App\Models\StudentAttendance;
use App\Models\Student;
use App\Models\NotificationLog;
use App\Models\User;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkAttendanceAction
{
    public function execute(array $data): ?StudentAttendance
    {
        // Find or create the attendance record for the student on the specified date
        $attendance = StudentAttendance::firstOrCreate([
            'student_id'   => $data['student_id'],
            'date'         => $data['date'],
            'subject_id'   => $data['subject_id'] ?? null,
        ], [
            'classroom_id' => $data['classroom_id'],
        ]);

        $previousStatus = $attendance->status?->value ?? $attendance->status;
        // Get the student's attendance percentage BEFORE updating
        $previousAttendancePercentage = $this->getStudentAttendancePercentage($data['student_id']);
        
        $attendance->update([
            'status'      => $data['status'],
            'recorded_by' => auth()->id(),
            'remarks'     => $data['remarks'] ?? null,
        ]);

        // After marking attendance, check and update the student's flag
        $this->updateStudentAttendanceFlag($data['student_id'], $previousAttendancePercentage);

        $newStatus = $attendance->fresh()->status?->value ?? $attendance->fresh()->status;
        if ($newStatus === AttendanceStatus::Absent->value && $previousStatus !== AttendanceStatus::Absent->value) {
            $this->notifyParentOfAbsence($attendance->fresh());
        }

        return $attendance;
    }

    /**
     * Get the student's current attendance percentage
     */
    private function getStudentAttendancePercentage(int $studentId): ?float
    {
        $presentValue = AttendanceStatus::Present->value;
        $excusedValue = AttendanceStatus::Excused->value;

        // Calculate overall attendance percentage for the student across all subjects
        $attendanceStats = DB::table('student_attendances')
            ->select(
                DB::raw('COUNT(*) as total_attendance'),
                DB::raw("SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("ROUND((SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as attendance_percentage")
            )
            ->where('student_id', $studentId)
            ->where('status', '<>', $excusedValue) // Exclude excused absences from calculation
            ->first();

        if ($attendanceStats->total_attendance > 0) {
            return (float)$attendanceStats->attendance_percentage;
        }
        
        return null;
    }

    /**
     * Update the student's is_flag based on their overall attendance percentage
     */
    private function updateStudentAttendanceFlag(int $studentId, ?float $previousAttendancePercentage): void
    {
        $presentValue = AttendanceStatus::Present->value;
        $excusedValue = AttendanceStatus::Excused->value;

        // Calculate overall attendance percentage for the student across all subjects
        $attendanceStats = DB::table('student_attendances')
            ->select(
                DB::raw('COUNT(*) as total_attendance'),
                DB::raw("SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("ROUND((SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as attendance_percentage")
            )
            ->where('student_id', $studentId)
            ->where('status', '<>', $excusedValue) // Exclude excused absences from calculation
            ->first();

        if ($attendanceStats->total_attendance > 0) {
            $attendancePercentage = (float)$attendanceStats->attendance_percentage;
            $absencePercentage = 100 - $attendancePercentage;

            // Determine if the student should be flagged (attendance below 70%)
            $shouldFlag = $attendancePercentage < 70;
            
            // Get the student to check if they were previously flagged
            $student = Student::find($studentId);
            if (!$student) {
                Log::error("Student with ID {$studentId} not found");
                return;
            }

            $wasPreviouslyFlagged = $student->is_flag;
            
            // Determine if notification is needed
            $needsNotification = false;
            
            // Notify if student is crossing the 70% threshold for the first time
            if ($shouldFlag && !$wasPreviouslyFlagged) {
                $needsNotification = true;
            } 
            // Notify if student was already below 70% and attendance got worse (decreased)
            elseif ($wasPreviouslyFlagged && $previousAttendancePercentage !== null && $attendancePercentage < $previousAttendancePercentage) {
                $needsNotification = true;
            }

            // Update the student's flag status
            $student->update(['is_flag' => $shouldFlag]);

            Log::info("Student {$studentId} attendance: {$attendancePercentage}% (was: {$previousAttendancePercentage}%), absence: {$absencePercentage}%, is_flag: " . ($shouldFlag ? 'true' : 'false'));

            // Send notification to principal if needed
            if ($needsNotification) {
                $this->sendLowAttendanceNotification($student, $attendancePercentage, $absencePercentage);
            }
        }
    }

    /**
     * Send notification to principal when student attendance falls below threshold or gets worse
     */
    private function sendLowAttendanceNotification(Student $student, float $attendancePercentage, float $absencePercentage): void
    {
        // Find the principal for this student's institution
        $principal = User::where('institution_id', $student->institution_id)
            ->where('role', UserRole::Principal)
            ->first();

        if ($principal) {
            // Get classroom name separately to avoid potential relationship loading issues
            $classroomName = DB::table('classrooms')
                ->where('id', $student->classroom_id)
                ->value('name');

            $title = 'Low Student Attendance Alert';
            $message = "{$student->first_name} {$student->sur_name} in {$classroomName} has low attendance of {$attendancePercentage}% (absence rate: {$absencePercentage}%). Immediate review required.";
            
            $notificationLog = NotificationLog::create([
                'user_id' => $principal->id,
                'type' => 'low_attendance_alert',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'meta' => [
                    'recipient_role' => 'principal',
                    'student_id' => (string) $student->id,
                    'student_name' => $student->first_name . ' ' . $student->sur_name,
                    'classroom_id' => (string) $student->classroom_id,
                    'classroom_name' => $classroomName,
                    'attendance_percentage' => (string) $attendancePercentage,
                    'absence_percentage' => (string) $absencePercentage,
                    'total_attendance_records' => (string) DB::table('student_attendances')
                        ->where('student_id', $student->id)
                        ->where('status', '<>', AttendanceStatus::Excused->value)
                        ->count(),
                    'present_records' => (string) DB::table('student_attendances')
                        ->where('student_id', $student->id)
                        ->where('status', AttendanceStatus::Present->value)
                        ->count(),
                    'absent_records' => (string) DB::table('student_attendances')
                        ->where('student_id', $student->id)
                        ->whereIn('status', [AttendanceStatus::Absent->value, AttendanceStatus::Late->value])
                        ->count(),
                ],
                'sent_at' => now($principal->timezone ?? 'UTC'),
            ]);

            // event(new NewNotificationEvent($notificationLog));

            // Send FCM notification if enabled and token exists
            if ($principal->notifications_enabled && $principal->fcm_token) {
                $firebaseNotificationService = app(FirebaseNotificationService::class);
                
                $sent = $firebaseNotificationService->sendToToken(
                    $principal->fcm_token,
                    $title,
                    $message,
                    [
                        'student_id' => (string) $student->id,
                        'student_name' => $student->first_name . ' ' . $student->sur_name,
                        'classroom_name' => $classroomName,
                        'attendance_percentage' => (string) $attendancePercentage,
                        'absence_percentage' => (string) $absencePercentage,
                    ]
                );

                if ($sent) {
                    Log::info("FCM notification sent to principal {$principal->id} for notification {$notificationLog->id}.");
                } else {
                    Log::warning("FCM notification was not sent to principal {$principal->id} for notification {$notificationLog->id}.");
                }
            } else {
                Log::info("Skipped FCM for principal {$principal->id} because the token is missing or notifications are disabled.");
            }
        }
    }

    private function notifyParentOfAbsence(StudentAttendance $attendance): void
    {
        $student = $attendance->student()->with('guardian', 'classroom')->first();
        if (!$student || !$student->guardian) {
            return;
        }

        $parent = $student->guardian;
        $classroomName = $student->classroom?->name ?? DB::table('classrooms')->where('id', $student->classroom_id)->value('name');
        $title = 'Student Marked Absent';
        $message = "{$student->full_name} was marked absent in {$classroomName} on {$attendance->date?->toDateString()}. Please provide a reason.";

        $notificationLog = NotificationLog::create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'type' => 'student_absent_alert',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'meta' => [
                'recipient_role' => 'parent',
                'student_id' => (string) $student->id,
                'student_name' => $student->full_name,
                'classroom_id' => (string) $student->classroom_id,
                'classroom_name' => $classroomName,
                'attendance_id' => (string) $attendance->id,
                'attendance_date' => $attendance->date?->toDateString(),
                'attendance_status' => 'absent',
            ],
            'sent_at' => now($parent->timezone ?? 'UTC'),
        ]);

        if ($parent->notifications_enabled && $parent->fcm_token) {
            $firebaseNotificationService = app(FirebaseNotificationService::class);

            $firebaseNotificationService->sendToToken(
                $parent->fcm_token,
                $title,
                $message,
                [
                    'type' => 'student_absent_alert',
                    'student_id' => (string) $student->id,
                    'student_name' => $student->full_name,
                    'classroom_name' => $classroomName,
                    'attendance_id' => (string) $attendance->id,
                    'attendance_date' => $attendance->date?->toDateString(),
                ]
            );
        }

        Log::info("Created absent notification {$notificationLog->id} for parent {$parent->id}.");
    }
}
