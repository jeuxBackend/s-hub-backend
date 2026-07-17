<?php

namespace App\Actions\Teacher;

use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use App\Support\TimetableEntryResolver;

class FindFreeTeachersAction
{
    /**
     * Find teachers who are free during a specific lecture time
     * 
     * @param int $lectureId The lecture/subject ID to check against
     * @param int $institutionId The institution ID to scope the search
     * @return array Array of free teacher IDs
     */
    public function handle(int $lectureId, int $institutionId): array
    {
        $lecture = Subject::where('id', $lectureId)
            ->where('institution_id', $institutionId)
            ->first();

        if (!$lecture) {
            return [];
        }

        $teacherTimezone = config('app.timezone', 'UTC');
        $resolver = app(TimetableEntryResolver::class);
        $entry = $resolver->resolveForToday($lecture, $teacherTimezone);

        if (!$entry) {
            return [];
        }

        $entry->loadMissing('teacher');
        $teacherTimezone = $entry->teacher?->timezone ?? config('app.timezone', 'UTC');
        [$lectureStart, $lectureEnd] = $resolver->buildDateTimeRange($entry, Carbon::now($teacherTimezone), $teacherTimezone);
        $weekday = (int) $entry->weekday;

        $allTeachers = User::where('institution_id', $institutionId)
            ->whereIn('role', ['teacher', 'school-admin'])
            ->pluck('id')
            ->toArray();

        if (empty($allTeachers)) {
            return [];
        }

        $busyTeachers = TimetableEntry::query()
            ->where('institution_id', $institutionId)
            ->whereIn('teacher_id', $allTeachers)
            ->where('weekday', $weekday)
            ->where('subject_id', '!=', $lectureId)
            ->get()
            ->filter(function (TimetableEntry $subjectEntry) use ($lectureStart, $lectureEnd, $resolver, $teacherTimezone) {
                [$subjectStart, $subjectEnd] = $resolver->buildDateTimeRange($subjectEntry, Carbon::now($teacherTimezone), $teacherTimezone);
                return !($lectureEnd->lessThanOrEqualTo($subjectStart) || $lectureStart->greaterThanOrEqualTo($subjectEnd));
            })
            ->pluck('teacher_id')
            ->unique()
            ->toArray();

        $proxyBusyTeachers = Subject::where('institution_id', $institutionId)
            ->whereIn('proxy_teacher_id', $allTeachers)
            ->where('is_proxy', true)
            ->whereNotNull('proxy_start_time')
            ->whereNotNull('proxy_end_time')
            ->get()
            ->filter(function ($subject) use ($lectureStart, $lectureEnd) {
                $proxyStart = Carbon::parse($subject->proxy_start_time);
                $proxyEnd = Carbon::parse($subject->proxy_end_time);

                // Check if there's any overlap between the proxy time and lecture time
                return !($lectureEnd->lessThanOrEqualTo($proxyStart) || $lectureStart->greaterThanOrEqualTo($proxyEnd));
            })
            ->pluck('proxy_teacher_id')
            ->unique()
            ->toArray();

        $allBusyTeachers = array_unique(array_merge($busyTeachers, $proxyBusyTeachers, [(int) $entry->teacher_id]));
        return array_values(array_diff($allTeachers, $allBusyTeachers));
    }
}
