<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FeeReminderSchedule;
use App\Actions\Parent\NotifyUnpaidFeeParentsAction;
use Illuminate\Support\Facades\Log;

class CheckScheduledFeeReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fee-reminders:check-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and send scheduled fee payment reminders to parents with unpaid invoices';

    protected NotifyUnpaidFeeParentsAction $notifyAction;

    /**
     * Create a new command instance.
     */
    public function __construct(NotifyUnpaidFeeParentsAction $notifyAction)
    {
        parent::__construct();
        $this->notifyAction = $notifyAction;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Checking scheduled fee reminders...');
        $this->info('Checking scheduled fee reminders...');

        // Get all enabled schedules
        $schedules = FeeReminderSchedule::where('is_enabled', true)->get();

        if ($schedules->isEmpty()) {
            $this->info('No enabled fee reminder schedules found.');
            Log::info('No enabled fee reminder schedules found.');
            return Command::SUCCESS;
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($schedules as $schedule) {
            try {
                // Check if it's time to send notification for this schedule
                if (!$schedule->isTimeToSend()) {
                    $this->line("Schedule ID {$schedule->id} for institution {$schedule->institution_id} - Not time to send yet.");
                    $skippedCount++;
                    continue;
                }

                // Check if already sent today
                if ($schedule->last_sent_at && $schedule->last_sent_at->isToday()) {
                    $this->line("Schedule ID {$schedule->id} for institution {$schedule->institution_id} - Already sent today.");
                    $skippedCount++;
                    continue;
                }

                $this->info("Sending fee reminders for institution {$schedule->institution_id}...");
                Log::info("Processing fee reminder schedule ID {$schedule->id} for institution {$schedule->institution_id}");

                // Send notifications
                $result = $this->notifyAction->handle(
                    $schedule->institution_id,
                    $schedule->title,
                    $schedule->message
                );

                // Mark as sent
                $schedule->markAsSent();

                $this->info("✓ Sent to {$result['notified']} parents ({$result['failed']} failed) - Total due: {$result['total_amount_due']}");
                Log::info("Fee reminders sent successfully", [
                    'schedule_id' => $schedule->id,
                    'institution_id' => $schedule->institution_id,
                    'notified' => $result['notified'],
                    'failed' => $result['failed'],
                    'total_due' => $result['total_amount_due'],
                ]);

                $processedCount++;

            } catch (\Exception $e) {
                $this->error("Error processing schedule ID {$schedule->id}: " . $e->getMessage());
                Log::error("Error processing fee reminder schedule ID {$schedule->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $skippedCount++;
            }
        }

        $this->info("Completed! Processed: {$processedCount}, Skipped: {$skippedCount}");
        Log::info("Fee reminder check completed", [
            'processed' => $processedCount,
            'skipped' => $skippedCount,
        ]);

        return Command::SUCCESS;
    }
}
