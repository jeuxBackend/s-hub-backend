<?php

namespace App\Support;

use App\Models\Subject;
use App\Models\TimetableEntry;
use Carbon\Carbon;

class TimetableEntryResolver
{
    public function resolveForDate(Subject $subject, Carbon $date, ?string $timezone = null): ?TimetableEntry
    {
        $localizedDate = $timezone ? $date->copy()->setTimezone($timezone) : $date->copy();

        return TimetableEntry::query()
            ->where('subject_id', $subject->id)
            ->where('weekday', $localizedDate->isoWeekday())
            ->orderBy('start_time')
            ->first();
    }

    public function resolveForToday(Subject $subject, ?string $timezone = null): ?TimetableEntry
    {
        return $this->resolveForDate($subject, Carbon::now($timezone ?? config('app.timezone', 'UTC')), $timezone);
    }

    public function buildDateTimeRange(TimetableEntry $entry, Carbon $referenceDate, ?string $timezone = null): array
    {
        $tz = $timezone ?? config('app.timezone', 'UTC');
        $localizedDate = $referenceDate->copy()->setTimezone($tz);

        $startFormat = strlen($entry->start_time) > 5 ? 'H:i:s' : 'H:i';
        $endFormat = strlen($entry->end_time) > 5 ? 'H:i:s' : 'H:i';

        $start = Carbon::createFromFormat($startFormat, $entry->start_time, $tz)
            ->setDate($localizedDate->year, $localizedDate->month, $localizedDate->day);
        $end = Carbon::createFromFormat($endFormat, $entry->end_time, $tz)
            ->setDate($localizedDate->year, $localizedDate->month, $localizedDate->day);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    public function weekdayName(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }
}
