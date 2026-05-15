<?php

namespace App\Actions\TuitionUpdate;

use App\Models\ScheduledTuitionUpdate;
use App\Models\User;

class ScheduleUpdateAction
{
    public function handle(array $data, User $requester)
    {
        // If Schedule Sent is ON
        if (!empty($data['is_scheduled']) && $data['is_scheduled'] == true) {
            return ScheduledTuitionUpdate::updateOrCreate(
                [
                    'institution_id' => $requester->institution_id,
                    'classroom_id'   => $data['classroom_id'] ?? null,
                    'year'           => $data['year'],
                    'semester'       => $data['semester'],
                ],
                [
                    'frequency'  => $data['frequency'] ?? 'once',
                    'is_active'  => true,
                    'created_by' => $requester->id,
                ]
            );
        }

        // If Schedule Sent is OFF, just send the notification immediately (simulation)
        // Here we would typically dispatch an Event or Job to send emails/SMS
        // For now, we just return a success message.
        return true;
    }
}
