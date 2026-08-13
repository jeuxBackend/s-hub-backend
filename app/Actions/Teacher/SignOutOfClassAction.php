<?php

namespace App\Actions\Teacher;

use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SignOutOfClassAction
{
    public function handle(User $teacher, int $subjectId): TeacherAttendance
    {
        $timezone = $teacher->timezone ?: config('app.timezone', 'UTC');

        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->whereDate('date', now($timezone)->toDateString())
            ->first();

        if (!$attendance) {
            throw ValidationException::withMessages([
                'subject_id' => ['No attendance record found for this class today. Mark attendance before signing out.'],
            ]);
        }

        if ($attendance->checked_out_at) {
            throw ValidationException::withMessages([
                'subject_id' => ['You have already signed out of this class.'],
            ]);
        }

        $attendance->update([
            'checked_out_at' => now(),
            'checkout_type' => 'manual',
        ]);

        return $attendance->refresh();
    }
}
