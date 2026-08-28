<?php

namespace App\Actions\Teacher;

use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SignOutOfClassAction
{
    public function handle(User $teacher, int $subjectId, ?float $latitude = null, ?float $longitude = null): TeacherAttendance
    {
        $timezone = $teacher->timezone ?: config('app.timezone', 'UTC');

        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->whereDate('date', now($timezone)->toDateString())
            ->with('subject.institution')
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

        if (!$teacher->remote_teaching) {
            if ($latitude === null || $longitude === null) {
                throw ValidationException::withMessages([
                    'latitude' => ['Latitude and longitude are required to sign out.'],
                ]);
            }

            $institution = $attendance->subject->institution;

            if (!$institution || !$institution->latitude || !$institution->longitude) {
                throw ValidationException::withMessages([
                    'latitude' => ['Institution location is not set. Please contact admin.'],
                ]);
            }

            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $institution->latitude,
                $institution->longitude
            );

            if ($distance > 300) {
                throw ValidationException::withMessages([
                    'latitude' => ['You are too far from the institution to sign out. You must be within 300 meters.'],
                ]);
            }
        }

        $attendance->update([
            'checked_out_at' => now(),
            'checkout_type' => 'manual',
        ]);

        return $attendance->refresh();
    }

    /**
     * Haversine distance between two coordinates, in meters.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
