<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class SendChatNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $message;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->message->load(['sender', 'conversation']);
        $conversation = $this->message->conversation;

        // Determine the receiver
        $receiverId = $conversation->user_one_id === $this->message->sender_id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        $receiver = \App\Models\User::find($receiverId);

        if (!$receiver || !$receiver->fcm_token || !$receiver->notifications_enabled) {
            return;
        }

        try {
            // Check if firebase credentials are provided
            $credentialsPath = storage_path('app/firebase/firebase_creds.json');

            if (env('FIREBASE_CREDENTIALS')) {
                $credentialsPath = env('FIREBASE_CREDENTIALS');
            }

            if (!file_exists($credentialsPath)) {
                \Log::warning("FCM credentials not found at $credentialsPath. Cannot send push notification.");
                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $senderName = $this->message->sender->full_name ?? 'Someone';

            $body = $this->message->body;
            if (!$body && $this->message->attachment) {
                $body = '📷 Sent an attachment';
            }

            $notification = Notification::create(
                "New message from $senderName",
                $body
            );

            $data = [
                'type' => 'chat_message',
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $this->message->id,
                'sender_id' => (string) $this->message->sender_id,
            ];

            $cloudMessage = CloudMessage::withTarget('token', $receiver->fcm_token)
                ->withNotification($notification)
                ->withData($data);

            $messaging->send($cloudMessage);

            \Log::info("FCM chat notification sent to user {$receiver->id}");

        } catch (\Exception $e) {
            \Log::error("FCM Send Error: " . $e->getMessage());
        }
    }
}
