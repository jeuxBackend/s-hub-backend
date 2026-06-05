<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;

class NewNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(NotificationLog $notification)
    {
        $this->notification = $notification;
        Log::info('NewNotificationEvent constructed', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'title' => $notification->title,
            'type' => $notification->type
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channel = new PrivateChannel('notifications.' . $this->notification->user_id);
        Log::info('NewNotificationEvent broadcasting on channel', [
            'channel' => $channel->name,
            'user_id' => $this->notification->user_id
        ]);
        
        return [$channel];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NewNotificationEvent';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        $payload = [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
            'meta' => $this->notification->meta,
            'sent_at' => clone $this->notification->sent_at,
        ];
        
        Log::info('NewNotificationEvent broadcast payload', $payload);
        
        return $payload;
    }

    /**
     * Handle broadcast failure.
     */
    public function broadcastFailed($exception): void
    {
        Log::error('NewNotificationEvent broadcast failed', [
            'notification_id' => $this->notification->id,
            'user_id' => $this->notification->user_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}