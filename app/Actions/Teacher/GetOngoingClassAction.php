<?php

namespace App\Actions\Teacher;

use App\Enums\UserRole;
use App\Models\TeacherAttendance;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Support\TimetableEntryResolver;
use Carbon\Carbon;

class GetOngoingClassAction
{
    public function __construct(private TimetableEntryResolver $resolver)
    {
    }

    /**
     * Returns the teacher's currently signed-in (not yet signed-out) class, or null.
     */
    public function handle(User $teacher): ?array
    {
        if ($teacher->role !== UserRole::Teacher) {
            return null;
        }

        $timezone = $teacher->timezone ?: config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);

        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', 'present')
            ->whereNull('checked_out_at')
            ->whereDate('date', $now->toDateString())
            ->with('subject.classroom')
            ->latest('id')
            ->first();

        if (!$attendance || !$attendance->subject) {
            return null;
        }

        $subject = $attendance->subject;

        $entry = TimetableEntry::query()
            ->where('institution_id', $attendance->institution_id)
            ->where('subject_id', $subject->id)
            ->where('teacher_id', $teacher->id)
            ->where('weekday', $now->isoWeekday())
            ->orderBy('start_time')
            ->first();

        [$startTime, $endTime] = $entry
            ? $this->resolver->buildDateTimeRange($entry, $now, $timezone)
            : [null, null];

        return [
            'attendance_id' => $attendance->id,
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'classroom_id' => $subject->classroom_id,
            'classroom_name' => $subject->classroom?->name,
            'checked_in_at' => $attendance->created_at?->toIso8601String(),
            'class_start_time' => $startTime?->toIso8601String(),
            'class_end_time' => $endTime?->toIso8601String(),
            'is_remote' => (bool) $attendance->is_remote,
        ];
    }
}
