<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Exception;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected ?Messaging $messaging = null;

    public function __construct()
    {
        $credentialsPath = env('FIREBASE_CREDENTIALS', storage_path('app/firebase/firebase-creds.json'));

        if (!file_exists($credentialsPath)) {
            Log::warning("Firebase credentials not found at {$credentialsPath}. Push notifications are disabled.");
            return;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send a notification to a specific device via FCM token.
     *
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [])
    {
        try {
            if (!$this->messaging) {
                Log::warning('Firebase messaging is not initialized. Skipping push notification.');
                return false;
            }

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            return $this->messaging->send($message);
        } catch (Exception $e) {
            Log::error('Firebase Notification Failed (Token): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification to a specific topic.
     *
     * @param string $topic
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|bool
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [])
    {
        try {
            if (!$this->messaging) {
                Log::warning('Firebase messaging is not initialized. Skipping topic notification.');
                return false;
            }

            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            return $this->messaging->send($message);
        } catch (Exception $e) {
            Log::error('Firebase Notification Failed (Topic): ' . $e->getMessage());
            return false;
        }
    }
}
