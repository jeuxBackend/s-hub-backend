<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboxUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationPayload;
    public $receiverId;

    /**
     * Create a new event instance.
     */
    public function __construct(array $conversationPayload, int $receiverId)
    {
        $this->conversationPayload = $conversationPayload;
        $this->receiverId = $receiverId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast on the receiver's private notifications channel
        return [
            new PrivateChannel('notifications.' . $this->receiverId),
        ];
    }

    /**
     * The event name the client listens for.
     */
    public function broadcastAs(): string
    {
        return 'inbox.updated';
    }

    /**
     * Payload sent to the client.
     */
    public function broadcastWith(): array
    {
        return $this->conversationPayload;
    }
}
