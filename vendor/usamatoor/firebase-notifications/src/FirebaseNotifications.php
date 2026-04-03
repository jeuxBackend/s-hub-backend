<?php

namespace Usama\FirebaseNotifications;

use Illuminate\Support\Facades\Cache;
use Usama\FirebaseNotifications\Exceptions\MissingConfigurationException;
use Usama\FirebaseNotifications\Exceptions\TokenRequestException;
use Usama\FirebaseNotifications\Exceptions\NotificationSendException;


class FirebaseNotifications
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected string $projectId;
    protected string $fcmEndpoint;

    public function __construct()
    {
        $this->clientId     = config('firebase-notifications.client_id');
        $this->clientSecret = config('firebase-notifications.client_secret');
        $this->refreshToken = config('firebase-notifications.refresh_token');
        $this->projectId    = config('firebase-notifications.project_id');
        $this->fcmEndpoint  = config('firebase-notifications.fcm_endpoint');

        $this->validateConfig();
    }

    protected function validateConfig(): void
    {
        if (!($this->clientId && $this->clientSecret && $this->refreshToken && $this->projectId)) {
            throw new MissingConfigurationException("Firebase credentials are missing in the config file.");
        }
    }

    protected function getAccessToken(): string
    {
        return Cache::remember('firebase_access_token', now()->addMinutes(58), function () {
            $postData = http_build_query([
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type'    => 'refresh_token',
            ]);

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new TokenRequestException("cURL error while fetching access token: $error");
            }
            curl_close($ch);

            $responseData = json_decode($response, true);
            if (!isset($responseData['access_token'])) {
                throw new TokenRequestException('Failed to get access token from Google: ' . $response);
            }

            return $responseData['access_token'];
        });
    }

    public function send(string $firebaseToken, string $title, string $body, array $optionalPayload = []): array
    {
        $accessToken = $this->getAccessToken();
        $endpoint    = str_replace(':project_id', $this->projectId, $this->fcmEndpoint);

        $payload = [
            'message' => [
                'token'        => $firebaseToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
            ],
        ];

        if (!empty($optionalPayload)) {
            $payload = $this->arrayMergeRecursiveDistinct($payload, $optionalPayload);
        }

        $payloadJson = json_encode($payload);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new NotificationSendException("cURL error while sending notification: $curlError");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new NotificationSendException("FCM request failed with status $httpCode: $response");
        }

        return json_decode($response, true);
    }

    protected function arrayMergeRecursiveDistinct(array &$array1, array &$array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
