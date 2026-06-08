<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeReminderSchedule extends Model
{
    protected $fillable = [
        'notification_time',
        'days_of_week',
        'title',
        'message',
        'is_enabled',
        'last_sent_at',
        'institution_id',
    ];

    protected $casts = [
        'notification_time' => 'string',
        'days_of_week' => 'array',
        'is_enabled' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Get the institution that owns the schedule.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Check if notification should be sent today based on schedule.
     */
    public function shouldSendToday(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // If no days specified, send every day
        if (empty($this->days_of_week)) {
            return true;
        }

        // Check if current day is in the scheduled days (1=Monday, 7=Sunday)
        $currentDay = now()->isoWeekday(); // Returns 1-7
        return in_array($currentDay, $this->days_of_week);
    }

    /**
     * Check if it's time to send notification.
     */
    public function isTimeToSend(): bool
    {
        if (!$this->shouldSendToday()) {
            return false;
        }

        $currentTime = now()->format('H:i');
        $scheduledTime = substr($this->notification_time, 0, 5); // Extract HH:MM

        // Allow a 5-minute window for execution
        return $currentTime >= $scheduledTime && $currentTime <= date('H:i', strtotime($scheduledTime . ' +5 minutes'));
    }

    /**
     * Update last sent timestamp.
     */
    public function markAsSent(): void
    {
        $this->update(['last_sent_at' => now()]);
    }
}
