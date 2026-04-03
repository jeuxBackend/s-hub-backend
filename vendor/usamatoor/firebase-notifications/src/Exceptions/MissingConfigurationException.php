<?php

namespace Usama\FirebaseNotifications\Exceptions;

use Exception;

class MissingConfigurationException extends Exception
{
    protected $message = 'Required configuration is missing for Firebase Notification.';
}