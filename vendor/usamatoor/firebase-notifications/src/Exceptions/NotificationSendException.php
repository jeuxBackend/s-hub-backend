<?php

namespace Usama\FirebaseNotifications\Exceptions;

use Exception;

class NotificationSendException extends Exception
{
    protected $message = 'Failed to send the Firebase notification.';
}
