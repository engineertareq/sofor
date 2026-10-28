<?php

namespace App\Exceptions;

use RuntimeException;

/** A text that could not be sent from somebody's SMS wallet. The message is safe to show. */
class SmsWalletException extends RuntimeException
{
    public static function insufficient(float $cost, float $balance): self
    {
        return new self(sprintf(
            'Not enough SMS balance: this text costs ৳%s and you have ৳%s. Top up your SMS wallet first.',
            number_format($cost, 2),
            number_format($balance, 2),
        ));
    }
}
