<?php

namespace App\Events;

use App\Models\TeacherAttendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TeacherAutoSignedOutEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public TeacherAttendance $attendance)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->attendance->teacher_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TeacherAutoSignedOutEvent';
    }

    public function broadcastWith(): array
    {
        $subjectName = $this->attendance->subject?->name;

        return [
            'attendance_id' => $this->attendance->id,
            'subject_id' => $this->attendance->subject_id,
            'subject_name' => $subjectName,
            'checked_out_at' => $this->attendance->checked_out_at?->toIso8601String(),
            'title' => 'Signed Out of Class',
            'message' => "You were automatically signed out of {$subjectName} because class time ended.",
        ];
    }
}
