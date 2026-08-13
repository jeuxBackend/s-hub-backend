<?php

namespace App\Console\Commands;

use App\Events\TeacherAutoSignedOutEvent;
use App\Models\NotificationLog;
use App\Models\TeacherAttendance;
use App\Models\TimetableEntry;
use App\Services\FirebaseNotificationService;
use App\Support\TimetableEntryResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoSignOutTeachers extends Command
{
    protected $signature = 'attendance:auto-signout-teachers';

    protected $description = 'Automatically sign teachers out of class once the scheduled class time has ended, if they forgot to sign out manually.';

    public function handle(FirebaseNotificationService $firebaseNotificationService, TimetableEntryResolver $resolver): int
    {
        \Log::info('Cron checking for teachers who forgot to sign out of class.');

        $openAttendances = TeacherAttendance::query()
            ->where('status', 'present')
            ->whereNull('checked_out_at')
            ->with('teacher', 'subject')
            ->get();

        $signedOutCount = 0;
        $skippedCount = 0;

        foreach ($openAttendances as $attendance) {
            $teacher = $attendance->teacher;
            $subject = $attendance->subject;

            if (!$teacher || !$subject) {
                $skippedCount++;
                continue;
            }

            $timezone = $teacher->timezone ?: config('app.timezone', 'UTC');
            $now = Carbon::now($timezone);
            $attendanceDate = Carbon::parse($attendance->date, $timezone);

            $entry = TimetableEntry::query()
                ->where('institution_id', $attendance->institution_id)
                ->where('subject_id', $subject->id)
                ->where('teacher_id', $teacher->id)
                ->where('weekday', $attendanceDate->isoWeekday())
                ->orderBy('start_time')
                ->first();

            if (!$entry) {
                $skippedCount++;
                continue;
            }

            [, $endTime] = $resolver->buildDateTimeRange($entry, $attendanceDate, $timezone);

            if ($now->lessThan($endTime)) {
                $skippedCount++;
                continue;
            }

            $attendance->update([
                'checked_out_at' => now(),
                'checkout_type' => 'auto',
            ]);

            $message = "You were automatically signed out of {$subject->name} because class time ended.";

            NotificationLog::create([
                'user_id' => $teacher->id,
                'type' => 'teacher_auto_signout',
                'title' => 'Signed Out of Class',
                'message' => $message,
                'is_read' => false,
                'meta' => [
                    'attendance_id' => (string) $attendance->id,
                    'subject_id' => (string) $subject->id,
                    'subject_name' => $subject->name,
                ],
                'sent_at' => now($timezone),
            ]);

            if ($teacher->notifications_enabled && $teacher->fcm_token) {
                $firebaseNotificationService->sendToToken(
                    $teacher->fcm_token,
                    'Signed Out of Class',
                    $message,
                    [
                        'type' => 'teacher_auto_signout',
                        'attendance_id' => (string) $attendance->id,
                        'subject_id' => (string) $subject->id,
                    ]
                );
            }

            event(new TeacherAutoSignedOutEvent($attendance->fresh(['subject'])));

            $signedOutCount++;
        }

        \Log::info("Auto-signout cron completed. Signed out: {$signedOutCount}, Skipped: {$skippedCount}");
        $this->info("Completed. Signed out: {$signedOutCount}, Skipped: {$skippedCount}");

        return Command::SUCCESS;
    }
}
