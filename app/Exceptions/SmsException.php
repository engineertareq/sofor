<?php

namespace App\Exceptions;

use RuntimeException;

/** A text message the SMS gateway did not accept. The message is safe to log, not to show. */
class SmsException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $gatewayCode = null)
    {
        parent::__construct($message);
    }
}
