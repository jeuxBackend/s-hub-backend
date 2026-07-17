<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TeacherAttendance;
use App\Models\NotificationLog;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Enums\AttendanceStatus;
use App\Events\NewNotificationEvent;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use App\Support\TimetableEntryResolver;

class NotifyPrincipalLateTeacher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:notify-principal-absent-teacher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify principal if a teacher is absent';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseNotificationService $firebaseNotificationService, TimetableEntryResolver $resolver)
    {
        // Use teacher's timezone for timing
        \Log::info('Cron checking for absent teachers (>= 3 mins late)');

        $entries = TimetableEntry::query()
            ->with(['subject', 'teacher', 'classroom', 'institution.principal'])
            ->get();

        $notifiedCount = 0;
        $skippedCount = 0;

        foreach ($entries as $entry) {
            $subject = $entry->subject;

            if (!$subject || !$entry->teacher || !$entry->institution || !$entry->institution->principal) {
                \Log::debug("Timetable entry {$entry->id} is missing teacher, institution, or principal, skipping");
                $skippedCount++;
                continue;
            }

            $teacher = $entry->teacher;
            $teacherTimezone = $teacher->timezone ?? 'UTC';
            $nowTeacher = Carbon::now($teacherTimezone);

            try {
                if ((int) $entry->weekday !== $nowTeacher->isoWeekday()) {
                    $skippedCount++;
                    continue;
                }

                [$classStartTime, $classEndTime] = $resolver->buildDateTimeRange($entry, $nowTeacher, $teacherTimezone);

                // Check if class started 3 or more minutes ago
                $latenessThreshold = $nowTeacher->copy()->subMinutes(3);

                if ($classStartTime->greaterThan($latenessThreshold)) {
                    \Log::debug("Subject {$subject->id} start time ({$classStartTime->format('H:i:s')}) is not yet 3 minutes past (teacher timezone {$teacherTimezone}), skipping");
                    $skippedCount++;
                    continue;
                }

                \Log::info("Subject {$subject->id} started at {$classStartTime->format('H:i:s')}, checking attendance...");

                // Check if attendance is marked for today
                $attendance = TeacherAttendance::where('teacher_id', $entry->teacher_id)
                    ->where('subject_id', $subject->id)
                    ->whereDate('date', $nowTeacher->toDateString())
                    ->first();

                if (!$attendance) {
                    \Log::info("No attendance found for teacher {$entry->teacher_id}, subject {$subject->id}, marking as absent");

                    // Auto‑mark attendance as absent
                    $attendance = \App\Models\TeacherAttendance::create([
                        'teacher_id' => $entry->teacher_id,
                        'subject_id' => $subject->id,
                        'institution_id' => $entry->institution_id,
                        'date' => $nowTeacher->toDateString(),
                        'status' => \App\Enums\AttendanceStatus::Absent->value,
                    ]);

                    // Teacher is absent (more than 3 minutes late) – notify principal
                    $principal = $entry->institution->principal;
                    $isInCharge = $entry->classroom && $entry->classroom->in_charge_id == $entry->teacher_id;
                    $attendanceRequestKey = NotificationLog::attendanceRequestKey(
                        (int) $subject->id,
                        $nowTeacher->toDateString(),
                        (int) $entry->teacher_id
                    );
                    // Expire any prior teacher_absent_alert notifications for this request key
                    \App\Models\NotificationLog::expireAttendanceRequest($attendanceRequestKey);

                    if ($principal) {
                        $title = 'Teacher Absent';
                        $message = "Teacher {$teacher->full_name} has not marked attendance for the class ({$subject->name}) scheduled at {$classStartTime->format('g:i a')}.";
                        $this->storeAndSendNotification(
                            recipient: $principal,
                            type: 'teacher_absent_alert',
                            title: $title,
                            message: $message,
                            meta: [
                                'recipient_role' => 'principal',
                                'teacher_id' => (string) $entry->teacher_id,
                                'teacher_name' => $teacher->full_name,
                                'subject_id' => (string) $subject->id,
                                'subject_name' => $subject->name,
                                'classroom_id' => (string) $entry->classroom_id,
                                'classroom_name' => $entry->classroom ? $entry->classroom->name : 'N/A',
                                'start_time' => $classStartTime->format('g:i a'),
                                'end_time' => $classEndTime->format('g:i a'),
                                'is_incharge' => $isInCharge ? 'Yes' : 'No',
                                'attendance_request_key' => $attendanceRequestKey,
                                'timezone' => $teacherTimezone,
                                'principal_timezone' => $principal->timezone ?? 'UTC',
                            ],
                            attendanceRequestKey: $attendanceRequestKey,
                            firebaseNotificationService: $firebaseNotificationService
                        );

                        \Log::info("Broadcasted absent teacher notification to principal {$principal->id} about teacher {$entry->teacher_id} for subject {$subject->id}.");
                        $this->info("Notified principal {$principal->id} about absent teacher {$entry->teacher_id} for subject {$subject->id}.");

                        $notifiedCount++;
                    }

                    if ($teacher instanceof User) {
                        $teacherTitle = 'Attendance Marked Absent';
                        $teacherMessage = "Your attendance was automatically marked absent for {$subject->name} because you were more than 3 minutes late.";

                        $this->storeAndSendNotification(
                            recipient: $teacher,
                            type: 'teacher_absent_self_alert',
                            title: $teacherTitle,
                            message: $teacherMessage,
                            meta: [
                                'recipient_role' => 'teacher',
                                'subject_id' => (string) $subject->id,
                                'subject_name' => $subject->name,
                                'classroom_id' => (string) $entry->classroom_id,
                                'classroom_name' => $entry->classroom ? $entry->classroom->name : 'N/A',
                                'start_time' => $classStartTime->format('g:i a'),
                                'end_time' => $classEndTime->format('g:i a'),
                                'attendance_status' => AttendanceStatus::Absent->value,
                                'attendance_request_key' => $attendanceRequestKey,
                                'timezone' => $teacherTimezone,
                            ],
                            attendanceRequestKey: $attendanceRequestKey,
                            firebaseNotificationService: $firebaseNotificationService
                        );

                        \Log::info("Broadcasted absent attendance notification to teacher {$teacher->id} for subject {$subject->id}.");
                        $notifiedCount++;
                    }
                } else {
                    \Log::debug("Attendance already exists for teacher {$entry->teacher_id}, subject {$subject->id}");
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                \Log::error("Error processing subject {$subject->id}: " . $e->getMessage());
                $skippedCount++;
            }
        }

        \Log::info("Absent teacher check completed. Notified: {$notifiedCount}, Skipped: {$skippedCount}");
        $this->info("Completed. Notified: {$notifiedCount}, Skipped: {$skippedCount}");
    }

    private function storeAndSendNotification(
        User $recipient,
        string $type,
        string $title,
        string $message,
        array $meta,
        string $attendanceRequestKey,
        FirebaseNotificationService $firebaseNotificationService
    ): NotificationLog {
        $log = NotificationLog::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'attendance_request_key' => $attendanceRequestKey,
            'meta' => $meta,
            'sent_at' => now($recipient->timezone ?? 'UTC'),
        ]);

        event(new NewNotificationEvent($log));

        if ($recipient->notifications_enabled && $recipient->fcm_token) {
            $sent = $firebaseNotificationService->sendToToken(
                $recipient->fcm_token,
                $title,
                $message,
                array_map(static fn($value) => (string) $value, $meta)
            );

            if ($sent) {
                \Log::info("FCM notification sent to user {$recipient->id} for notification {$log->id}.");
            } else {
                \Log::warning("FCM notification was not sent to user {$recipient->id} for notification {$log->id}.");
            }
        } else {
            \Log::info("Skipped FCM for user {$recipient->id} because the token is missing or notifications are disabled.");
        }

        return $log;
    }
}
