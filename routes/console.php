<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tuition:process-scheduled')->daily();
Schedule::command('birthday:notify-teachers')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('attendance:notify-teachers')->everyMinute()->withoutOverlapping();
Schedule::command('attendance:auto-signout-teachers')->everyMinute()->withoutOverlapping();
Schedule::command('attendance:notify-principal-absent-teacher')->everyMinute()->withoutOverlapping();
Schedule::command('attendance:reassign-missed-proxy')->everyThreeMinutes()->withoutOverlapping();