<?php

namespace App\Actions\Teacher;

use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;

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
        // Get the lecture to check its time
        $lecture = Subject::where('id', $lectureId)
            ->where('institution_id', $institutionId)
            ->select('id', 'start_time', 'end_time', 'teacher_id')
            ->first();

        if (!$lecture || !$lecture->start_time || !$lecture->end_time) {
            return [];
        }

        // Parse the lecture times
        $lectureStart = Carbon::parse($lecture->start_time);
        $lectureEnd = Carbon::parse($lecture->end_time);

        // Get all teachers in the institution (including school admins)
        $allTeachers = User::where('institution_id', $institutionId)
            ->whereIn('role', ['teacher', 'school-admin'])
            ->pluck('id')
            ->toArray();

        if (empty($allTeachers)) {
            return [];
        }

        // Get teachers who have classes during this time range
        $busyTeachers = Subject::where('institution_id', $institutionId)
            ->whereIn('teacher_id', $allTeachers)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('id', '!=', $lectureId) // Exclude the current lecture itself
            ->get()
            ->filter(function ($subject) use ($lectureStart, $lectureEnd) {
                $subjectStart = Carbon::parse($subject->start_time);
                $subjectEnd = Carbon::parse($subject->end_time);

                // Check if there's any overlap between the subject time and lecture time
                // Two time ranges overlap if one starts before the other ends
                return !($lectureEnd->lessThanOrEqualTo($subjectStart) || $lectureStart->greaterThanOrEqualTo($subjectEnd));
            })
            ->pluck('teacher_id')
            ->unique()
            ->toArray();

        // Also get teachers who have active proxy assignments during this time
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

        // Combine busy teachers (regular classes + proxy assignments)
        $allBusyTeachers = array_unique(array_merge($busyTeachers, $proxyBusyTeachers));

        // Filter free teachers
        $freeTeachers = array_values(array_diff($allTeachers, $allBusyTeachers));

        return $freeTeachers;
    }
}
