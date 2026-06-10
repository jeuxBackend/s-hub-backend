<?php

namespace App\Actions\Teacher;

use App\Models\Subject;
use App\Models\User;
use App\Models\TeacherAttendance;
use Carbon\Carbon;

class FindFreeTeachersAction
{
    /**
     * Find teachers who are free during a specific lecture time
     * 
     * @param int $lectureId Subject/Lecture ID
     * @param int $institutionId
     * @return array Array of free teacher IDs
     */
    public function handle(int $lectureId, int $institutionId)
    {
        $lecture = Subject::find($lectureId);

        if (!$lecture || !$lecture->start_time || !$lecture->end_time) {
            return [];
        }

        // Parse the lecture times
        $lectureStart = Carbon::parse($lecture->start_time);
        $lectureEnd = Carbon::parse($lecture->end_time);

        // Get today's date
        $today = Carbon::today();

        // Get all teachers in the same institution
        $allTeachers = User::where('institution_id', $institutionId)
            ->whereIn('role', ['teacher', 'school-admin'])
            ->pluck('id')
            ->toArray();

        if (empty($allTeachers)) {
            return [];
        }

        // Get all teachers who have regular classes during this time
        $busyTeachers = Subject::where('institution_id', $institutionId)
            ->where('id', '!=', $lectureId)
            ->whereIn('teacher_id', $allTeachers)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->filter(function ($subject) use ($lectureStart, $lectureEnd) {
                $subjectStart = Carbon::parse($subject->start_time);
                $subjectEnd = Carbon::parse($subject->end_time);

                // Check if there's any overlap
                return !($lectureEnd->lessThanOrEqualTo($subjectStart) || $lectureStart->greaterThanOrEqualTo($subjectEnd));
            })
            ->pluck('teacher_id')
            ->unique()
            ->toArray();

        // Also treat active proxy assignments as busy during the proxy window
        $busyProxyTeachers = Subject::where('institution_id', $institutionId)
            ->where('id', '!=', $lectureId)
            ->whereIn('proxy_teacher_id', $allTeachers)
            ->where('is_proxy', true)
            ->whereNotNull('proxy_start_time')
            ->whereNotNull('proxy_end_time')
            ->get()
            ->filter(function ($subject) use ($lectureStart, $lectureEnd) {
                $proxyStart = Carbon::parse($subject->proxy_start_time);
                $proxyEnd = Carbon::parse($subject->proxy_end_time);

                return !($lectureEnd->lessThanOrEqualTo($proxyStart) || $lectureStart->greaterThanOrEqualTo($proxyEnd));
            })
            ->pluck('proxy_teacher_id')
            ->unique()
            ->toArray();

        // Free teachers are all teachers minus regular busy teachers and proxy-busy teachers
        $busyTeachers = array_unique(array_merge($busyTeachers, $busyProxyTeachers));
        $freeTeachers = array_diff($allTeachers, $busyTeachers);

        return array_values($freeTeachers);
    }
}
