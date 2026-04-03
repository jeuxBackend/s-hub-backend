<?php

namespace Usama\FirebaseNotifications\Exceptions;

use Exception;

class TokenRequestException extends Exception
{
    protected $message = 'Failed to retrieve access token from Google.';
}