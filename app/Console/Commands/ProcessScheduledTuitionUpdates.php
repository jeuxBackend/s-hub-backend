<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScheduledTuitionUpdate;
use Illuminate\Support\Carbon;

class ProcessScheduledTuitionUpdates extends Command
{
    protected $signature = 'tuition:process-scheduled';
    protected $description = 'Process and send scheduled tuition update notifications';

    public function handle()
    {
        $this->info('Processing scheduled tuition updates...');

        $schedules = ScheduledTuitionUpdate::where('is_active', true)->get();

        foreach ($schedules as $schedule) {
            if ($this->shouldSend($schedule)) {
                $this->sendNotification($schedule);
                
                $schedule->last_sent_at = now();
                
                if ($schedule->frequency === 'once') {
                    $schedule->is_active = false;
                }
                
                $schedule->save();
            }
        }

        $this->info('Scheduled tuition updates processed successfully.');
    }

    private function shouldSend(ScheduledTuitionUpdate $schedule)
    {
        if (!$schedule->last_sent_at) {
            return true;
        }

        $lastSent = Carbon::parse($schedule->last_sent_at);

        switch ($schedule->frequency) {
            case 'monthly':
                return now()->diffInMonths($lastSent) >= 1;
            case 'after_6_months':
                return now()->diffInMonths($lastSent) >= 6;
            case 'yearly':
                return now()->diffInYears($lastSent) >= 1;
            case 'once':
                return false; // Already sent if last_sent_at is set
            default:
                return false;
        }
    }

    private function sendNotification(ScheduledTuitionUpdate $schedule)
    {
        // Integration point for sending SMS, Email, or Push notifications.
        // E.g., dispatching an event: event(new TuitionUpdateScheduledEvent($schedule));
        $this->line("Sent update for Classroom ID: {$schedule->classroom_id}, Year: {$schedule->year}");
    }
}
