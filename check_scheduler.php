<?php

/**
 * Scheduler Diagnostic Script
 * Run with: php check_scheduler.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

echo "========================================\n";
echo "Laravel Scheduler Diagnostics\n";
echo "========================================\n\n";

// 1. Check system cron
echo "1. System Cron Daemon:\n";
$cronProcesses = [];
exec('ps aux | grep "[c]ron -f"', $cronProcesses);
if (!empty($cronProcesses)) {
    echo "   ✅ Cron daemon is running\n";
    foreach ($cronProcesses as $process) {
        echo "   Process: {$process}\n";
    }
} else {
    echo "   ❌ Cron daemon is NOT running\n";
    echo "   Fix: sudo service cron start\n";
}
echo "\n";

// 2. Check crontab configuration
echo "2. Crontab Configuration:\n";
$crontab = [];
exec('crontab -l 2>/dev/null', $crontab);
$scheduleFound = false;
foreach ($crontab as $line) {
    if (strpos($line, 'artisan schedule:run') !== false || strpos($line, 'schedule:work') !== false) {
        echo "   ✅ Found: {$line}\n";
        $scheduleFound = true;
    }
}
if (!$scheduleFound) {
    echo "   ❌ No Laravel scheduler entry found in crontab\n";
    echo "   Fix: Add this to crontab:\n";
    echo "   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1\n";
}
echo "\n";

// 3. Check registered scheduled commands
echo "3. Registered Scheduled Commands:\n";
$scheduleOutput = [];
exec('php artisan schedule:list 2>&1', $scheduleOutput);
foreach ($scheduleOutput as $line) {
    if (trim($line) !== '') {
        echo "   {$line}\n";
    }
}
echo "\n";

// 4. Check for stuck processes
echo "4. Checking for Stuck Processes:\n";
$stuckProcesses = [];
exec('ps aux | grep "artisan attendance" | grep -v grep', $stuckProcesses);
if (!empty($stuckProcesses)) {
    echo "   ⚠️  WARNING: Found potentially stuck processes:\n";
    foreach ($stuckProcesses as $process) {
        echo "   {$process}\n";
        
        // Extract PID and elapsed time
        if (preg_match('/\s+(\d+)\s+.*?(\d+:\d{2}:\d{2})\s+/', $process, $matches)) {
            $pid = $matches[1];
            $elapsed = $matches[2];
            echo "   PID: {$pid}, Elapsed: {$elapsed}\n";
            
            // Warn if running more than 5 minutes
            $parts = explode(':', $elapsed);
            $minutes = isset($parts[1]) ? intval($parts[1]) : 0;
            if ($minutes > 5) {
                echo "   ❌ This process has been running for too long!\n";
                echo "   Fix: kill {$pid}\n";
            }
        }
    }
} else {
    echo "   ✅ No stuck processes found\n";
}
echo "\n";

// 5. Check recent execution logs
echo "5. Recent Scheduler Executions:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    // Check for teacher attendance notifications
    preg_match_all('/\[([^\]]+)\].*Cron checking teacher attendance at ([^\s]+)/', $logContent, $attendanceMatches);
    
    if (!empty($attendanceMatches[0])) {
        echo "   Teacher Attendance Notifications:\n";
        $recentExecutions = array_slice($attendanceMatches[0], -5);
        foreach ($recentExecutions as $execution) {
            echo "   - {$execution}\n";
        }
        
        // Check frequency
        if (count($attendanceMatches[0]) > 0) {
            $lastExecution = end($attendanceMatches[1]);
            $lastTime = Carbon::parse($lastExecution);
            $minutesAgo = $lastTime->diffInMinutes(now());
            
            if ($minutesAgo <= 2) {
                echo "   ✅ Last execution was {$minutesAgo} minute(s) ago (healthy)\n";
            } else {
                echo "   ⚠️  Last execution was {$minutesAgo} minutes ago (may indicate issue)\n";
            }
        }
    } else {
        echo "   ⚠️  No teacher attendance notification logs found\n";
    }
    
    echo "\n";
    
    // Check for errors
    preg_match_all('/\[([^\]]+)\].*ERROR.*notification/i', $logContent, $errorMatches);
    if (!empty($errorMatches[0])) {
        echo "   Recent Notification Errors:\n";
        $recentErrors = array_slice($errorMatches[0], -3);
        foreach ($recentErrors as $error) {
            echo "   - {$error}\n";
        }
    } else {
        echo "   ✅ No recent notification errors\n";
    }
} else {
    echo "   ⚠️  Log file not found\n";
}
echo "\n";

// 6. Check Reverb status
echo "6. Laravel Reverb Status:\n";
$reverbProcesses = [];
exec('ps aux | grep "[r]everb:start"', $reverbProcesses);
if (!empty($reverbProcesses)) {
    echo "   ✅ Reverb server is running\n";
    foreach ($reverbProcesses as $process) {
        echo "   {$process}\n";
    }
} else {
    echo "   ❌ Reverb server is NOT running\n";
    echo "   Fix: php artisan reverb:start --host=" . env('REVERB_HOST', 'localhost') . "\n";
}
echo "\n";

// 7. Recommendations
echo "========================================\n";
echo "Recommendations:\n";
echo "========================================\n";

if (!$scheduleFound) {
    echo "❌ Add Laravel scheduler to crontab:\n";
    echo "   crontab -e\n";
    echo "   Add: * * * * * cd " . base_path() . " && php artisan schedule:run >> /dev/null 2>&1\n\n";
}

if (!empty($stuckProcesses)) {
    echo "❌ Kill stuck processes identified above\n\n";
}

if (empty($reverbProcesses)) {
    echo "❌ Start Reverb server:\n";
    echo "   php artisan reverb:start --host=" . env('REVERB_HOST', 'localhost') . "\n\n";
}

echo "✅ To test scheduler manually:\n";
echo "   php artisan schedule:run\n\n";

echo "✅ To monitor logs in real-time:\n";
echo "   tail -f storage/logs/laravel.log | grep -i 'cron\\|notification'\n\n";

echo "✅ To view scheduled commands:\n";
echo "   php artisan schedule:list\n\n";

echo "Diagnostic complete!\n";
