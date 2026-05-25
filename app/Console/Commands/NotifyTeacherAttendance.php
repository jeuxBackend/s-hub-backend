<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subject;
use App\Models\NotificationLog;
use App\Events\NewNotificationEvent;
use Carbon\Carbon;

class NotifyTeacherAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:notify-teachers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify teachers when it is time to mark attendance for their scheduled classes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i'); // 24-hour format

        \Log::info("Cron checking teacher attendance at $currentTime");

        $subjects = Subject::whereNotNull('start_time')->with('teacher', 'classroom')->get();

        foreach ($subjects as $subject) {
            if (!$subject->teacher) {
                continue;
            }

            try {
                $startTime = Carbon::parse($subject->start_time)->format('H:i');
            } catch (\Exception $e) {
                continue;
            }

            // If the current time matches the subject's start time
            if ($startTime === $currentTime) {
                
                $title = 'Time for Class!';
                $message = "It is time for your class ({$subject->name}) in {$subject->classroom?->name}. Please mark your attendance.";
                
                $log = NotificationLog::create([
                    'user_id' => $subject->teacher_id,
                    'type' => 'teacher_attendance_reminder',
                    'title' => $title,
                    'message' => $message,
                    'meta' => [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'classroom_id' => $subject->classroom_id,
                        'classroom_name' => $subject->classroom ? $subject->classroom->name : 'N/A',
                        'start_time' => $subject->start_time
                    ],
                    'sent_at' => now(),
                ]);

                // Broadcast
                event(new NewNotificationEvent($log));

                $this->info("Notified teacher {$subject->teacher_id} for subject {$subject->id}.");
                \Log::info("Broadcasted attendance notification to teacher {$subject->teacher_id} for subject {$subject->id}.");
            }
        }
    }
}
