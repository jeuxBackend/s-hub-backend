# Usage

```php
use Usama\FirebaseNotifications\FirebaseNotifications;

$notifier = new FirebaseNotifications();

$response = $notifier->send(
    $firebaseToken,
    'Test Title',
    'This is the body message',
    ['data' => ['customKey' => 'value']]
);
```

Optional payload can include `data`, `android`, `apns`, etc.
