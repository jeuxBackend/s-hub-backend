# Configuration

Publish the config file:

```
php artisan vendor:publish --tag=firebase-notifications-config
```

This creates `config/firebase-notifications.php`:

```php
return [
    'client_id'     => env('FIREBASE_CLIENT_ID'),
    'client_secret' => env('FIREBASE_CLIENT_SECRET'),
    'refresh_token' => env('FIREBASE_REFRESH_TOKEN'),
    'project_id'    => env('FIREBASE_PROJECT_ID'),
    'fcm_endpoint'  => 'https://fcm.googleapis.com/v1/projects/:project_id/messages:send',
];
```

Set values in your `.env` file.
