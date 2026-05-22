<?php

namespace App\Actions\Attendance;

use App\Models\StudentAttendance;

class MarkAttendanceAction
{
    public function execute(array $data): ?StudentAttendance
    {
        // Find or create the attendance record for the student on the specified date
        $attendance = StudentAttendance::firstOrCreate([
            'student_id'   => $data['student_id'],
            'date'         => $data['date'],
            'subject_id'   => $data['subject_id'] ?? null,
        ], [
            'classroom_id' => $data['classroom_id'],
        ]);

        $attendance->update([
            'status'      => $data['status'],
            'recorded_by' => auth()->id(),
            'remarks'     => $data['remarks'] ?? null,
        ]);

        return $attendance;
    }
}
