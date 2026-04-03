<?php

namespace App\Actions\Attendance;

use App\Models\StudentAttendance;

class MarkAttendanceAction
{
    public function execute(array $data): ?StudentAttendance
    {
        $attendance = StudentAttendance::where('student_id', $data['student_id'])
            ->whereDate('date', $data['date'])
            ->first();

        if (! $attendance) {
            // Optional: throw exception or return null
            return null;
        }

        $attendance->update([
            'status'     => $data['status'],
            'recorded_by'  => auth()->id(),
            'remarks'    => $data['remarks'] ?? null,
        ]);

        return $attendance;
    }
}
