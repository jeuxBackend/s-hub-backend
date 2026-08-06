<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyTeacherBirthdays extends Command
{
    protected $signature = 'birthday:notify-teachers';

    protected $description = 'Send birthday wishes to teachers and notify principals about teacher birthdays.';

    public function handle(FirebaseNotificationService $firebaseNotificationService): int
    {
        \Log::info('Cron checking teacher birthdays.');

        $teachers = User::query()
            ->where('role', UserRole::Teacher->value)
            ->where('status', true)
            ->whereNotNull('dob')
            ->with(['institution.principal'])
            ->get();

        $teacherNotificationsSent = 0;
        $principalNotificationsSent = 0;
        $skippedCount = 0;

        foreach ($teachers as $teacher) {
            $timezone = $teacher->timezone ?: config('app.timezone', 'UTC');
            $todayInTeacherTimezone = Carbon::now($timezone);
            $tomorrowInTeacherTimezone = $todayInTeacherTimezone->copy()->addDay();
            $birthday = $teacher->dob;

            if (!$birthday) {
                $skippedCount++;
                continue;
            }

            $isBirthdayToday = (int) $birthday->month === (int) $todayInTeacherTimezone->month
                && (int) $birthday->day === (int) $todayInTeacherTimezone->day;

            $isBirthdayTomorrow = (int) $birthday->month === (int) $tomorrowInTeacherTimezone->month
                && (int) $birthday->day === (int) $tomorrowInTeacherTimezone->day;

            if (!$isBirthdayToday && !$isBirthdayTomorrow) {
                $skippedCount++;
                continue;
            }

            $institution = $teacher->institution;
            $principal = $institution?->principal;

            if ($isBirthdayToday) {
                $birthdayDate = $todayInTeacherTimezone->toDateString();

                if ($this->teacherNotificationAlreadySent($teacher->id, $birthdayDate)) {
                    \Log::info("Birthday wish already sent to teacher {$teacher->id} for {$birthdayDate}, skipping.");
                } else {
                    $teacherTitle = 'Happy Birthday!';
                    $teacherMessage = "Happy Birthday, {$teacher->full_name}! Wishing you a wonderful day filled with joy and success.";

                    $this->storeAndSendNotification(
                        recipient: $teacher,
                        type: 'teacher_birthday_wish',
                        title: $teacherTitle,
                        message: $teacherMessage,
                        meta: [
                            'recipient_role' => 'teacher',
                            'birthday_date' => $birthdayDate,
                            'teacher_id' => (string) $teacher->id,
                            'teacher_name' => $teacher->full_name,
                            'timezone' => $timezone,
                        ],
                        firebaseNotificationService: $firebaseNotificationService
                    );

                    $teacherNotificationsSent++;
                }
            }

            if ($isBirthdayTomorrow) {
                if (!$principal instanceof User) {
                    \Log::info("Teacher {$teacher->id} has no principal linked to institution {$teacher->institution_id}, skipping principal notification.");
                } else {
                    $upcomingBirthdayDate = $tomorrowInTeacherTimezone->toDateString();

                    if ($this->principalNotificationAlreadySent($principal->id, $teacher->id, $upcomingBirthdayDate)) {
                        \Log::info("Birthday alert already sent to principal {$principal->id} for teacher {$teacher->id} on {$upcomingBirthdayDate}, skipping.");
                    } else {
                        $principalTitle = 'Teacher Birthday Tomorrow';
                        $principalMessage = "{$teacher->full_name} has a birthday tomorrow.";

                        $this->storeAndSendNotification(
                            recipient: $principal,
                            type: 'teacher_birthday_wish',
                            title: $principalTitle,
                            message: $principalMessage,
                            meta: [
                                'recipient_role' => 'principal',
                                'birthday_date' => $upcomingBirthdayDate,
                                'teacher_id' => (string) $teacher->id,
                                'teacher_name' => $teacher->full_name,
                                'teacher_timezone' => $timezone,
                                'principal_timezone' => $principal->timezone ?? config('app.timezone', 'UTC'),
                                'institution_id' => (string) $teacher->institution_id,
                                'institution_name' => $institution?->name ?? 'N/A',
                            ],
                            firebaseNotificationService: $firebaseNotificationService
                        );

                        $principalNotificationsSent++;
                    }
                }
            }
        }

        \Log::info("Teacher birthday cron completed. Teacher notifications: {$teacherNotificationsSent}, Principal notifications: {$principalNotificationsSent}, Skipped: {$skippedCount}");
        $this->info("Completed. Teacher notifications: {$teacherNotificationsSent}, Principal notifications: {$principalNotificationsSent}, Skipped: {$skippedCount}");

        return Command::SUCCESS;
    }

    private function teacherNotificationAlreadySent(int $teacherId, string $birthdayDate): bool
    {
        return NotificationLog::query()
            ->where('user_id', $teacherId)
            ->where('type', 'teacher_birthday_wish')
            ->whereJsonContains('meta->birthday_date', $birthdayDate)
            ->exists();
    }

    private function principalNotificationAlreadySent(int $principalId, int $teacherId, string $birthdayDate): bool
    {
        return NotificationLog::query()
            ->where('user_id', $principalId)
            ->where('type', 'teacher_birthday_wish')
            ->whereJsonContains('meta->birthday_date', $birthdayDate)
            ->whereJsonContains('meta->teacher_id', (string) $teacherId)
            ->exists();
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
            'sent_at' => now($recipient->timezone ?? config('app.timezone', 'UTC')),
        ]);

        if ($recipient->notifications_enabled && $recipient->fcm_token) {
            $sent = $firebaseNotificationService->sendToToken(
                $recipient->fcm_token,
                $title,
                $message,
                array_map(static fn($value) => (string) $value, $meta)
            );

            if ($sent) {
                \Log::info("Birthday FCM sent to user {$recipient->id} for notification {$log->id}.");
            } else {
                \Log::warning("Birthday FCM was not sent to user {$recipient->id} for notification {$log->id}.");
            }
        } else {
            \Log::info("Skipped birthday FCM for user {$recipient->id} because the token is missing or notifications are disabled.");
        }

        return $log;
    }
}
