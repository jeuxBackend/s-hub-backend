<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        //
    }

    /**
     * Broadcast on a private channel per conversation.
     * Only the two participants can subscribe (enforced in channels.php).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->conversation_id),
        ];
    }

    /**
     * The event name the client listens for.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Payload sent to the client (Flutter / React).
     */
    public function broadcastWith(): array
    {
        $message = $this->message->load('sender');

        return [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender' => [
                'id'              => $message->sender->id,
                'full_name'       => $message->sender->full_name,
                'profile_picture' => $message->sender->profile_picture,
            ],
            'body'            => $message->body,
            'attachment'      => $message->attachment_url,
            'attachment_type' => $message->attachment_type,
            'read_at'         => $message->read_at?->toISOString(),
            'created_at'      => $message->created_at->toISOString(),
        ];
    }
}
