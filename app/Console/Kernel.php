<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Auto‑load all commands in app/Console/Commands
        $this->load(__DIR__ . '/Commands');

        // Also load routes/console.php (the file where you defined the schedule)
        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // The schedule definitions are already in routes/console.php,
        // but you can also repeat them here if you prefer:
        // $schedule->command('attendance:notify-teachers')->everyMinute();
    }
}
