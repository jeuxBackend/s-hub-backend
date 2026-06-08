<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subject;
use App\Models\TeacherAttendance;
use App\Models\NotificationLog;
use App\Models\User;
use App\Enums\AttendanceStatus;
use App\Events\NewNotificationEvent;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;

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
    public function handle(FirebaseNotificationService $firebaseNotificationService)
    {
        $now = Carbon::now();

        \Log::info('Cron checking for absent teachers (>= 3 mins late)');

        // Get all subjects with start_time
        $subjects = Subject::whereNotNull('start_time')
            ->with(['teacher', 'classroom', 'institution.principal'])
            ->get();

        $notifiedCount = 0;
        $skippedCount = 0;

        foreach ($subjects as $subject) {
            if (!$subject->teacher || !$subject->institution || !$subject->institution->principal) {
                \Log::debug("Subject {$subject->id} missing teacher, institution, or principal, skipping");
                $skippedCount++;
                continue;
            }

            try {
                // Parse the start_time and set it to today's date
                $classStartTime = Carbon::parse($subject->start_time);
                $classStartTime->setDate($now->year, $now->month, $now->day);

                // Check if class started 3 or more minutes ago
                $latenessThreshold = $now->copy()->subMinutes(3);

                if ($classStartTime->greaterThan($latenessThreshold)) {
                    \Log::debug("Subject {$subject->id} start time ({$classStartTime->format('H:i:s')}) is not yet 3 minutes past, skipping");
                    $skippedCount++;
                    continue;
                }

                \Log::info("Subject {$subject->id} started at {$classStartTime->format('H:i:s')}, checking attendance...");

                // Check if attendance is marked for today
                $attendance = TeacherAttendance::where('teacher_id', $subject->teacher_id)
                    ->where('subject_id', $subject->id)
                    ->whereDate('date', $now->toDateString())
                    ->first();

                if (!$attendance) {
                    \Log::info("No attendance found for teacher {$subject->teacher_id}, subject {$subject->id}, marking as absent");

                    // Auto‑mark attendance as absent
                    $attendance = \App\Models\TeacherAttendance::create([
                        'teacher_id' => $subject->teacher_id,
                        'subject_id' => $subject->id,
                        'institution_id' => $subject->institution_id,
                        'date' => $now->toDateString(),
                        'status' => \App\Enums\AttendanceStatus::Absent->value,
                    ]);

                    // Teacher is absent (more than 3 minutes late) – notify principal
                    $principal = $subject->institution->principal;
                    $isInCharge = $subject->classroom && $subject->classroom->in_charge_id == $subject->teacher_id;

                    if ($principal) {
                        $title = 'Teacher Absent';
                        $message = "Teacher {$subject->teacher->full_name} has not marked attendance for the class ({$subject->name}) scheduled at {$subject->start_time}.";
                        $this->storeAndSendNotification(
                            recipient: $principal,
                            type: 'teacher_absent_alert',
                            title: $title,
                            message: $message,
                            meta: [
                                'recipient_role' => 'principal',
                                'teacher_id' => (string) $subject->teacher_id,
                                'teacher_name' => $subject->teacher->full_name,
                                'subject_id' => (string) $subject->id,
                                'subject_name' => $subject->name,
                                'classroom_id' => (string) $subject->classroom_id,
                                'classroom_name' => $subject->classroom ? $subject->classroom->name : 'N/A',
                                'start_time' => (string) $subject->start_time,
                                'end_time' => (string) $subject->end_time,
                                'is_incharge' => $isInCharge ? 'Yes' : 'No',
                            ],
                            firebaseNotificationService: $firebaseNotificationService
                        );

                        \Log::info("Broadcasted absent teacher notification to principal {$principal->id} about teacher {$subject->teacher_id} for subject {$subject->id}.");
                        $this->info("Notified principal {$principal->id} about absent teacher {$subject->teacher_id} for subject {$subject->id}.");

                        $notifiedCount++;
                    }

                    $teacher = $subject->teacher;
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
                                'classroom_id' => (string) $subject->classroom_id,
                                'classroom_name' => $subject->classroom ? $subject->classroom->name : 'N/A',
                                'start_time' => (string) $subject->start_time,
                                'end_time' => (string) $subject->end_time,
                                'attendance_status' => AttendanceStatus::Absent->value,
                            ],
                            firebaseNotificationService: $firebaseNotificationService
                        );

                        \Log::info("Broadcasted absent attendance notification to teacher {$teacher->id} for subject {$subject->id}.");
                        $notifiedCount++;
                    }
                } else {
                    \Log::debug("Attendance already exists for teacher {$subject->teacher_id}, subject {$subject->id}");
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
        FirebaseNotificationService $firebaseNotificationService
    ): NotificationLog {
        $log = NotificationLog::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'meta' => $meta,
            'sent_at' => now(),
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
