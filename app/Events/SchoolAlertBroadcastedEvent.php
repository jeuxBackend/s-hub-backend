<?php

namespace App\Events;

use App\Models\SchoolAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SchoolAlertBroadcastedEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public SchoolAlert $alert)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('school-alerts.' . $this->alert->institution_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SchoolAlertBroadcastedEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'institution_id' => $this->alert->institution_id,
            'type' => $this->alert->type,
            'status' => $this->alert->status,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'confirmation_count' => $this->alert->confirmation_count,
            'confirmed_at' => $this->alert->confirmed_at,
            'resolved_at' => $this->alert->resolved_at,
            'meta' => $this->alert->meta,
            'created_by' => $this->alert->created_by,
            'confirmed_by' => $this->alert->confirmed_by,
            'resolved_by' => $this->alert->resolved_by,
            'created_at' => $this->alert->created_at,
            'updated_at' => $this->alert->updated_at,
        ];
    }
}
