<?php

namespace App\Support;

use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableCsvExporter
{
    public function download(Collection $entries, TimetableEntryResolver $resolver, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($entries, $resolver) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Weekday',
                'Period',
                'Start Time',
                'End Time',
                'Subject',
                'Classroom',
                'Teacher',
                'Entry Type',
                'Academic Year',
                'Term',
                'Locked',
            ]);

            $entries->each(function (TimetableEntry $entry) use ($handle, $resolver) {
                fputcsv($handle, [
                    $resolver->weekdayName((int) $entry->weekday),
                    (int) $entry->period_number,
                    $entry->start_time,
                    $entry->end_time,
                    $entry->subject?->name,
                    $entry->classroom?->name,
                    $entry->teacher?->full_name,
                    $entry->entry_type,
                    $entry->academic_year,
                    $entry->term,
                    $entry->is_locked ? 'Yes' : 'No',
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
