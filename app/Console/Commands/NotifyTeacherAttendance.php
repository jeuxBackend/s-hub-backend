<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationLog;
use App\Models\TimetableEntry;
use App\Events\NewNotificationEvent;
use App\Services\FirebaseNotificationService;
use App\Enums\UserRole;
use Carbon\Carbon;
use App\Support\TimetableEntryResolver;

class NotifyTeacherAttendance extends Command
{
    protected $signature = 'attendance:notify-teachers';

    protected $description = 'Notify teachers when it is time to mark attendance for their scheduled classes.';

    public function handle(FirebaseNotificationService $firebaseNotificationService, TimetableEntryResolver $resolver)
    {
        \Log::info("IS API HIT OR NOT ?");
        $now = Carbon::now();

        \Log::info("Cron checking teacher attendance at " . $now->format('H:i:s'));

        $entries = TimetableEntry::query()
            ->with(['subject', 'teacher', 'classroom'])
            ->get();

        \Log::info("Found " . $entries->count() . " timetable entries");

        $notifiedCount = 0;
        $skippedCount = 0;

        foreach ($entries as $entry) {

            if (!$entry->teacher || !$entry->subject) {
                \Log::debug("Timetable entry {$entry->id} is missing a teacher or subject, skipping");
                $skippedCount++;
                continue;
            }

            $teacher = $entry->teacher;
            $subject = $entry->subject;
            $teacherId = $teacher->id ?? $entry->teacher_id;

            if (empty($teacherId)) {
                \Log::warning("Subject {$subject->id} has no teacher ID, skipping");
                $skippedCount++;
                continue;
            }

            // Determine current time in teacher's timezone
            $teacherTimezone = $teacher->timezone ?? 'UTC';
            $nowTeacher = Carbon::now($teacherTimezone);

            try {
                if ((int) $entry->weekday !== $nowTeacher->isoWeekday()) {
                    $skippedCount++;
                    continue;
                }

                [$start, $end] = $resolver->buildDateTimeRange($entry, $nowTeacher, $teacherTimezone);
            } catch (\Exception $e) {
                \Log::error("Invalid timetable entry {$entry->id}: " . $e->getMessage());
                $skippedCount++;
                continue;
            }

            // Trigger only in the same minute in teacher's local time
            if ($start->format('H:i') !== $nowTeacher->format('H:i')) {
                \Log::debug("Subject {$subject->id} start time ({$start->format('H:i')}) does not match teacher's current time ({$nowTeacher->format('H:i')}), skipping");
                $skippedCount++;
                continue;
            }

            \Log::info("Subject {$subject->id} matches teacher's current time, checking for duplicates...");

            // Prevent duplicate notifications (using teacher's local date)
            $alreadySent = NotificationLog::where('user_id', $teacherId)
                ->where('type', 'teacher_attendance_reminder')
                ->whereDate('sent_at', $nowTeacher->toDateString())
                ->whereJsonContains('meta->subject_id', $subject->id)
                ->exists();

            if ($alreadySent) {
                \Log::info("Notification already sent to teacher {$teacherId} for subject {$subject->id} today, skipping");
                $skippedCount++;
                continue;
            }

            $title = 'Time for Class!';
            $message = "It is time for your class ({$subject->name}) in {$entry->classroom?->name}. Please mark your attendance.";
            $attendanceRequestDate = $nowTeacher->toDateString();
            $attendanceRequestKey = NotificationLog::attendanceRequestKey(
                (int) $subject->id,
                $attendanceRequestDate,
                (int) $teacherId
            );

            if (NotificationLog::attendanceRequestCompleted((int) $subject->id, $attendanceRequestDate, (int) $teacherId)) {
                \Log::info("Attendance already completed for teacher {$teacherId}, subject {$subject->id}; skipping reminder");
                $skippedCount++;
                continue;
            }

            try {
                $log = NotificationLog::create([
                    'user_id' => $teacherId,
                    'type' => 'teacher_attendance_reminder',
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false,
                    'attendance_request_key' => $attendanceRequestKey,
                    'meta' => [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'classroom_id' => $entry->classroom_id,
                        'classroom_name' => $entry->classroom?->name ?? 'N/A',
                        'start_time' => $start->format('g:i a'),
                        'end_time' => $end->format('g:i a'),
                        'weekday' => $entry->weekday,
                        'attendance_request_key' => $attendanceRequestKey,
                        'timezone' => $teacherTimezone,
                    ],
                    'sent_at' => now($teacherTimezone),
                ]);

                \Log::info("NotificationLog created with ID: {$log->id}, firing NewNotificationEvent...");

                event(new NewNotificationEvent($log));

                \Log::info("NewNotificationEvent fired successfully for teacher {$teacherId}, subject {$subject->id}");

                // Send Firebase push notification if the teacher has FCM token, notifications enabled, and has the correct role
                if (
                    $subject->teacher &&
                    $subject->teacher->fcm_token &&
                    $subject->teacher->notifications_enabled &&
                    ($subject->teacher->isRole(UserRole::Teacher) || $subject->teacher->isRole(UserRole::SchoolAdmin))
                ) {

                    $sent = $firebaseNotificationService->sendToToken(
                        $subject->teacher->fcm_token,
                        $title,
                        $message,
                        [
                            'type' => 'teacher_attendance_reminder',
                            'subject_id' => (string) $subject->id,
                            'subject_name' => $subject->name,
                            'classroom_name' => $entry->classroom?->name ?? 'N/A',
                            'start_time' => $start->format('g:i a'),
                            'end_time' => $end->format('g:i a'),
                        ]
                    );

                    if ($sent) {
                        \Log::info("FCM notification sent to teacher {$teacherId} for subject {$subject->id}");
                    } else {
                        \Log::warning("FCM notification failed to send to teacher {$teacherId} for subject {$subject->id}");
                    }
                } else {
                    $roleInfo = $subject->teacher ? $subject->teacher->role->value : 'null';
                    \Log::info("Skipped FCM for teacher {$teacherId} (role: {$roleInfo}) because token is missing, notifications disabled, or role is not teacher/school-admin");
                }

                $this->info("Notified teacher {$teacherId} for subject {$subject->id}");

                $notifiedCount++;

            } catch (\Exception $e) {
                \Log::error("Failed sending notification for subject {$subject->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $this->error("Failed: " . $e->getMessage());
            }
        }

        \Log::info("Teacher attendance notification completed. Notified: {$notifiedCount}, Skipped: {$skippedCount}");
        $this->info("Completed. Notified: {$notifiedCount}, Skipped: {$skippedCount}");

        return Command::SUCCESS;
    }
}
