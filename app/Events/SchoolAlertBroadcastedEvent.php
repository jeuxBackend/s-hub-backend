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
        $title = $this->alert->title;
        $message = $this->alert->message;
        $action = $this->alert->status === 'potential' && $this->alert->type === 'abduction'
            ? 'confirm_abduction'
            : ($this->alert->status === 'resolved' ? 'resolved' : 'active');

        if ($this->alert->status === 'potential' && $this->alert->type === 'abduction') {
            $title = 'Confirm Abduction Alert';
            $message = 'A potential abduction alert has been raised and requires confirmation from another teacher, school-admin, or principal.';
        } elseif ($this->alert->status === 'resolved') {
            $title = 'School Alert Resolved';
            
            $resolverName = null;
            if ($this->alert->resolvedBy) {
                $resolverName = $this->alert->resolvedBy->full_name 
                    ?? trim(($this->alert->resolvedBy->first_name ?? '') . ' ' . ($this->alert->resolvedBy->sure_name ?? ''));
            }
            
            $message = sprintf(
                '%s alert "%s" has been resolved%s.',
                ucfirst($this->alert->type),
                $this->alert->title,
                $resolverName ? ' by ' . $resolverName : ''
            );
        }

        return [
            'id' => $this->alert->id,
            'institution_id' => $this->alert->institution_id,
            'type' => $this->alert->type,
            'status' => $this->alert->status,
            'title' => $title,
            'message' => $message,
            'action' => $action,
            'requires_confirmation' => $this->alert->status === 'potential' && $this->alert->type === 'abduction',
            'exclude_user_id' => $this->alert->status === 'potential' && $this->alert->type === 'abduction'
                ? $this->alert->created_by
                : null,
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
