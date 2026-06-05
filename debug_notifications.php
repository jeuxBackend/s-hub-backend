<?php

/**
 * Diagnostic script for teacher notification system
 * Run with: php debug_notifications.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subject;
use App\Models\NotificationLog;
use Carbon\Carbon;

echo "========================================\n";
echo "Teacher Notification System Diagnostics\n";
echo "========================================\n\n";

// 1. Check current time
$now = Carbon::now();
echo "1. Current Time: " . $now->format('Y-m-d H:i:s') . "\n";
echo "   Time (HH:MM): " . $now->format('H:i') . "\n\n";

// 2. Check subjects with start_time
$subjects = Subject::whereNotNull('start_time')
    ->with('teacher', 'classroom')
    ->get();

echo "2. Subjects with start_time: " . $subjects->count() . "\n";

if ($subjects->isEmpty()) {
    echo "   ⚠️  WARNING: No subjects found with start_time!\n";
    echo "   Create some subjects with scheduled times first.\n\n";
} else {
    echo "   Sample subjects:\n";
    foreach ($subjects->take(5) as $subject) {
        $hasTeacher = $subject->teacher ? '✓' : '✗';
        echo "   - ID: {$subject->id}, Name: {$subject->name}\n";
        echo "     Start Time: {$subject->start_time}, Teacher: {$hasTeacher}\n";
        if ($subject->teacher) {
            echo "     Teacher ID: {$subject->teacher->id}, Name: {$subject->teacher->full_name}\n";
        }
        echo "\n";
    }
}

// 3. Check subjects matching current time
$matchingSubjects = $subjects->filter(function ($subject) use ($now) {
    try {
        $start = Carbon::parse($subject->start_time);
        return $start->format('H:i') === $now->format('H:i');
    } catch (\Exception $e) {
        return false;
    }
});

echo "3. Subjects matching current time ({$now->format('H:i')}): " . $matchingSubjects->count() . "\n";

if ($matchingSubjects->isEmpty()) {
    echo "   ⚠️  No subjects match the current time.\n";
    echo "   Notifications will only be sent when a subject's start_time matches the current minute.\n\n";
    
    // Show next upcoming subjects
    $upcoming = $subjects->filter(function ($subject) use ($now) {
        try {
            $start = Carbon::parse($subject->start_time);
            return $start->gt($now);
        } catch (\Exception $e) {
            return false;
        }
    })->sortBy(function ($subject) {
        return Carbon::parse($subject->start_time);
    })->take(3);
    
    if ($upcoming->isNotEmpty()) {
        echo "   Next upcoming subjects:\n";
        foreach ($upcoming as $subject) {
            echo "   - {$subject->name} at {$subject->start_time}\n";
        }
        echo "\n";
    }
} else {
    echo "   ✓ These subjects should receive notifications:\n";
    foreach ($matchingSubjects as $subject) {
        echo "   - {$subject->name} (ID: {$subject->id})\n";
    }
    echo "\n";
}

// 4. Check for duplicate notifications today
echo "4. Checking for duplicate notifications today...\n";
$today = $now->toDateString();
foreach ($matchingSubjects as $subject) {
    if (!$subject->teacher) {
        echo "   - Subject {$subject->id}: No teacher assigned\n";
        continue;
    }
    
    $teacherId = $subject->teacher->id;
    $alreadySent = NotificationLog::where('user_id', $teacherId)
        ->where('type', 'teacher_attendance_reminder')
        ->whereDate('sent_at', $today)
        ->whereJsonContains('meta->subject_id', $subject->id)
        ->exists();
    
    if ($alreadySent) {
        echo "   ⚠️  Subject {$subject->id}: Notification already sent today (blocked by duplicate prevention)\n";
    } else {
        echo "   ✓ Subject {$subject->id}: No duplicate found, notification can be sent\n";
    }
}
echo "\n";

// 5. Check Reverb configuration
echo "5. Broadcasting Configuration:\n";
echo "   BROADCAST_CONNECTION: " . config('broadcasting.default') . "\n";
echo "   REVERB_HOST: " . env('REVERB_HOST', 'not set') . "\n";
echo "   REVERB_PORT: " . env('REVERB_PORT', 'not set') . "\n";
echo "   REVERB_SCHEME: " . env('REVERB_SCHEME', 'not set') . "\n\n";

// 6. Check queue configuration
echo "6. Queue Configuration:\n";
echo "   QUEUE_CONNECTION: " . config('queue.default') . "\n\n";

// 7. Recommendations
echo "========================================\n";
echo "Recommendations:\n";
echo "========================================\n";

if ($subjects->isEmpty()) {
    echo "❌ Create subjects with start_time and assign teachers\n";
}

if ($matchingSubjects->isEmpty()) {
    echo "⏰ Wait until a subject's start_time matches the current time, or update a subject's start_time to current time for testing\n";
}

echo "📡 Ensure Reverb server is running: php artisan reverb:start\n";
echo "📝 Check logs after running command: tail -f storage/logs/laravel.log\n";
echo "🔧 Run command manually for testing: php artisan attendance:notify-teachers\n";
echo "🌐 Verify frontend is subscribed to channel: notifications.{teacher_id}\n";
echo "\n";

echo "Diagnostic complete!\n";
