<?php

namespace App\Console\Commands;

use App\Services\SchoolAlertService;
use Illuminate\Console\Command;

class AutoResolveExpiredAlerts extends Command
{
    protected $signature = 'alerts:auto-resolve-expired';

    protected $description = 'Automatically mark expired abduction alerts as resolved.';

    public function handle(SchoolAlertService $schoolAlertService): int
    {
        $resolvedCount = $schoolAlertService->autoResolveExpiredAlerts();

        $this->info("Auto-resolved {$resolvedCount} expired alert(s).");

        return Command::SUCCESS;
    }
}
