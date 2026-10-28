<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Six digit codes emailed to confirm an address. The rules every code follows
 * are in OneTimeCodes; email can take a while to arrive, so these last longer
 * than a text's.
 */
class EmailCodes extends OneTimeCodes
{
    public const TTL_MINUTES = 15;

    protected function destination(string $address): string
    {
        return mb_strtolower(trim($address));
    }

    protected function deliver(string $purpose, string $destination, string $code, ?User $user, string $field): void
    {
        try {
            // To the account when there is one, so it is written in their language.
            ($user ?? Notification::route('mail', $destination))->notify(new VerifyEmailAddress($code));
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([$field => __('We could not send the email just now. Please try again in a minute.')]);
        }
    }

    protected function tooManyMessage(): string
    {
        return __('Too many codes went to this email. Please try again in an hour.');
    }
}
