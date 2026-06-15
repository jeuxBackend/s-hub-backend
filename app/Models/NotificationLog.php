<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\TeacherAttendance;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'is_expired',
        'attendance_request_key',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_read' => 'boolean',
        'is_expired' => 'boolean',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (NotificationLog $notification): void {
            if (!$notification->user_id) {
                $notification->sent_at = $notification->sent_at ?? now();
                return;
            }

            $recipientTimezone = User::query()
                ->whereKey($notification->user_id)
                ->value('timezone') ?? config('app.timezone', 'UTC');

            $notification->sent_at = Carbon::now($recipientTimezone);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function attendanceRequestKey(int $subjectId, string $date, int $teacherId): string
    {
        return 'attendance:' . $teacherId . ':' . $subjectId . ':' . $date;
    }

    public static function expireAttendanceRequest(string $attendanceRequestKey): int
    {
        return static::where('attendance_request_key', $attendanceRequestKey)
            ->update(['is_expired' => true]);
    }

    public static function attendanceRequestCompleted(int $subjectId, string $date, int $teacherId): bool
    {
        return TeacherAttendance::where('subject_id', $subjectId)
            ->whereDate('date', $date)
            ->where(function ($query) use ($teacherId) {
                $query->where(function ($attendanceQuery) use ($teacherId) {
                    $attendanceQuery->where('teacher_id', $teacherId)
                        ->where('status', 'present');
                })
                ->orWhere('status', 'proxy');
            })
            ->exists();
    }
}
