<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subject;
use App\Models\NotificationLog;
use App\Events\NewNotificationEvent;
use Carbon\Carbon;

class NotifyTeacherAttendance extends Command
{
    protected $signature = 'attendance:notify-teachers';

    protected $description = 'Notify teachers when it is time to mark attendance for their scheduled classes.';

    public function handle()
    {
        $now = Carbon::now();

        \Log::info("Cron checking teacher attendance at " . $now->format('H:i:s'));

        $subjects = Subject::whereNotNull('start_time')
            ->with('teacher', 'classroom')
            ->get();

        \Log::info("Found " . $subjects->count() . " subjects with start_time");

        $notifiedCount = 0;
        $skippedCount = 0;

        foreach ($subjects as $subject) {

            if (!$subject->teacher) {
                \Log::debug("Subject {$subject->id} has no teacher assigned, skipping");
                $skippedCount++;
                continue;
            }

            $teacherId = $subject->teacher->id ?? $subject->teacher_id;

            if (empty($teacherId)) {
                \Log::warning("Subject {$subject->id} has no teacher ID, skipping");
                $skippedCount++;
                continue;
            }

            try {
                $start = Carbon::parse($subject->start_time);
            } catch (\Exception $e) {
                \Log::error("Invalid start_time for subject {$subject->id}: " . $e->getMessage());
                $skippedCount++;
                continue;
            }

            // Trigger only in the same minute
            if ($start->format('H:i') !== $now->format('H:i')) {
                \Log::debug("Subject {$subject->id} start time ({$start->format('H:i')}) does not match current time ({$now->format('H:i')}), skipping");
                $skippedCount++;
                continue;
            }

            \Log::info("Subject {$subject->id} matches current time, checking for duplicates...");

            // Prevent duplicate notifications
            $alreadySent = NotificationLog::where('user_id', $teacherId)
                ->where('type', 'teacher_attendance_reminder')
                ->whereDate('sent_at', $now->toDateString())
                ->whereJsonContains('meta->subject_id', $subject->id)
                ->exists();

            if ($alreadySent) {
                \Log::info("Notification already sent to teacher {$teacherId} for subject {$subject->id} today, skipping");
                $skippedCount++;
                continue;
            }

            $title = 'Time for Class!';
            $message = "It is time for your class ({$subject->name}) in {$subject->classroom?->name}. Please mark your attendance.";
            $attendanceRequestDate = $now->toDateString();
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
                        'classroom_id' => $subject->classroom_id,
                        'classroom_name' => $subject->classroom?->name ?? 'N/A',
                        'start_time' => $subject->start_time,
                        'attendance_request_key' => $attendanceRequestKey,
                    ],
                    'sent_at' => now(),
                ]);

                \Log::info("NotificationLog created with ID: {$log->id}, firing NewNotificationEvent...");

                event(new NewNotificationEvent($log));

                \Log::info("NewNotificationEvent fired successfully for teacher {$teacherId}, subject {$subject->id}");

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
