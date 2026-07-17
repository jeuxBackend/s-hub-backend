<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\SchoolTimetableConfig;

class TimetableViewFormatter
{
    public function groupWeekly(array $entries, TimetableEntryResolver $resolver, ?SchoolTimetableConfig $config = null): array
    {
        return $this->withBreakEntries($entries, $config)
            ->groupBy('weekday')
            ->map(function ($dayEntries, $weekday) use ($resolver) {
                $sorted = $this->sortEntries($dayEntries);

                return [
                    'weekday' => (int) $weekday,
                    'weekday_name' => $resolver->weekdayName((int) $weekday),
                    'entries' => $sorted->all(),
                ];
            })
            ->sortBy('weekday')
            ->values()
            ->all();
    }

    public function buildDaily(array $entries, Carbon $date, TimetableEntryResolver $resolver, ?SchoolTimetableConfig $config = null): array
    {
        $weekday = $date->isoWeekday();
        $filtered = $this->sortEntries(
            $this->withBreakEntries($entries, $config)
                ->filter(fn ($entry) => (int) ($entry['weekday'] ?? 0) === $weekday)
        )->all();

        return [
            'date' => $date->toDateString(),
            'weekday' => $weekday,
            'weekday_name' => $resolver->weekdayName($weekday),
            'entries' => $filtered,
        ];
    }

    public function buildMonthly(array $entries, Carbon $referenceDate, TimetableEntryResolver $resolver, ?SchoolTimetableConfig $config = null): array
    {
        $start = $referenceDate->copy()->startOfMonth();
        $end = $referenceDate->copy()->endOfMonth();
        $days = [];
        $entriesWithBreaks = $this->withBreakEntries($entries, $config);

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $weekday = $date->isoWeekday();
            $dayEntries = $this->sortEntries(
                $entriesWithBreaks->filter(fn ($entry) => (int) ($entry['weekday'] ?? 0) === $weekday)
            )->all();

            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $weekday,
                'weekday_name' => $resolver->weekdayName($weekday),
                'entries' => $dayEntries,
            ];
        }

        return [
            'month' => (int) $referenceDate->month,
            'month_name' => $referenceDate->format('F'),
            'year' => (int) $referenceDate->year,
            'days' => $days,
        ];
    }

    public function buildYearly(array $entries, int $year, TimetableEntryResolver $resolver, ?SchoolTimetableConfig $config = null): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $referenceDate = Carbon::create($year, $month, 1);
            $months[] = $this->buildMonthly($entries, $referenceDate, $resolver, $config);
        }

        return [
            'year' => $year,
            'months' => $months,
        ];
    }

    private function withBreakEntries(array $entries, ?SchoolTimetableConfig $config = null): Collection
    {
        return collect($entries)->merge($this->breakDisplayEntries($config));
    }

    private function sortEntries(Collection $entries): Collection
    {
        return $entries
            ->sortBy(fn ($entry) => implode('|', [
                $entry['sort_start_time'] ?? date('H:i:s', strtotime($entry['start_time'] ?? '00:00:00')),
                $entry['sort_end_time'] ?? date('H:i:s', strtotime($entry['end_time'] ?? '00:00:00')),
                $entry['entry_type'] ?? 'lesson',
                str_pad((string) ($entry['period_number'] ?? 0), 4, '0', STR_PAD_LEFT),
                str_pad((string) data_get($entry, 'classroom.id', 0), 10, '0', STR_PAD_LEFT),
            ]))
            ->map(function ($entry) {
                unset($entry['sort_start_time'], $entry['sort_end_time']);
                return $entry;
            })
            ->values();
    }

    private function breakDisplayEntries(?SchoolTimetableConfig $config): array
    {
        if (!$config) {
            return [];
        }

        $workingDays = $config->workingDays
            ->filter(fn ($day) => (bool) $day->is_open)
            ->pluck('weekday')
            ->map(fn ($weekday) => (int) $weekday)
            ->values();

        return $config->breakPeriods
            ->flatMap(function ($break) use ($workingDays) {
                $weekdays = $break->weekday
                    ? collect([(int) $break->weekday])
                    : $workingDays;

                return $weekdays->map(fn (int $weekday) => [
                    'id' => null,
                    'config_id' => $break->config_id,
                    'academic_year' => null,
                    'term' => null,
                    'weekday' => $weekday,
                    'weekday_name' => null,
                    'period_number' => null,
                    'start_time' => date('h:i a', strtotime($break->start_time)),
                    'end_time' => date('h:i a', strtotime($break->end_time)),
                    'sort_start_time' => $break->start_time,
                    'sort_end_time' => $break->end_time,
                    'entry_type' => 'break',
                    'is_break' => true,
                    'break' => [
                        'id' => $break->id,
                        'name' => $break->name,
                        'break_type' => $break->break_type ?? 'break',
                    ],
                    'subject' => null,
                    'teacher' => null,
                    'classroom' => null,
                ]);
            })
            ->values()
            ->all();
    }
}
