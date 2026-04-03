<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google API Credentials
    |--------------------------------------------------------------------------
    |
    | These values are used to authenticate with Google's OAuth2 service
    | and send notifications through Firebase Cloud Messaging.
    |
    */

    'client_id' => env('FIREBASE_CLIENT_ID'),
    'client_secret' => env('FIREBASE_CLIENT_SECRET'),
    'refresh_token' => env('FIREBASE_REFRESH_TOKEN'),
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | FCM Endpoint
    |--------------------------------------------------------------------------
    */

    'fcm_endpoint' => 'https://fcm.googleapis.com/v1/projects/' . env('FIREBASE_PROJECT_ID') . '/messages:send',


];
