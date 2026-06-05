<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subject;
use App\Models\TeacherAttendance;
use App\Models\NotificationLog;
use App\Events\NewNotificationEvent;
use Carbon\Carbon;

class NotifyPrincipalLateTeacher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:notify-principal-late-teacher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify principal if a teacher is 15 or more minutes late to a class or has not attended.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        // The time exactly 15 minutes ago
        $timeToCheck = $now->copy()->subMinutes(1)->format('H:i');

        \Log::info("Cron checking for late teachers (15 mins late) for start_time = $timeToCheck");

        // Get subjects that started exactly 15 minutes ago
        $subjects = Subject::whereNotNull('start_time')
            ->with(['teacher', 'classroom', 'institution.principal'])
            ->get();

        foreach ($subjects as $subject) {
            if (!$subject->teacher || !$subject->institution || !$subject->institution->principal) {
                continue;
            }

            try {
                $startTime = Carbon::parse($subject->start_time)->format('H:i');
            } catch (\Exception $e) {
                continue;
            }

            // We check if startTime is exactly 15 minutes ago
            // This prevents spamming notifications every minute after they are late
            if ($startTime === $timeToCheck) {

                // Check if attendance is marked for today
                $attendance = TeacherAttendance::where('teacher_id', $subject->teacher_id)
                    ->where('subject_id', $subject->id)
                    ->whereDate('date', $now->toDateString())
                    ->first();

                if (!$attendance) {
                    // Teacher is late or hasn't attended, notify principal
                    $principal = $subject->institution->principal;

                    if ($principal) {
                        $title = 'Teacher Late / Absent';
                        $message = "Teacher {$subject->teacher->full_name} has not marked attendance for the class ({$subject->name}) scheduled at {$subject->start_time}. They are 15 or more minutes late.";

                        $log = NotificationLog::create([
                            'user_id' => $principal->id,
                            'type' => 'teacher_late_alert',
                            'title' => $title,
                            'message' => $message,
                            'meta' => [
                                'teacher_id' => $subject->teacher_id,
                                'teacher_name' => $subject->teacher->full_name,
                                'subject_id' => $subject->id,
                                'subject_name' => $subject->name,
                                'classroom_id' => $subject->classroom_id,
                                'classroom_name' => $subject->classroom ? $subject->classroom->name : 'N/A',
                                'start_time' => $subject->start_time
                            ],
                            'sent_at' => now(),
                        ]);

                        // Broadcast notification
                        event(new NewNotificationEvent($log));

                        $this->info("Notified principal {$principal->id} about late teacher {$subject->teacher_id} for subject {$subject->id}.");
                        \Log::info("Broadcasted late teacher notification to principal {$principal->id} about teacher {$subject->teacher_id} for subject {$subject->id}.");
                    }
                }
            }
        }
    }
}
