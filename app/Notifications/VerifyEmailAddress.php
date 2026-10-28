<?php

namespace App\Notifications;

use App\Services\EmailCodes;
use App\Support\MailName;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your Sofor code": the six digit code that confirms an email address (see
 * EmailCodes). The code is in the subject too, so it shows in the inbox
 * without the email even being opened.
 */
class VerifyEmailAddress extends Notification
{
    public function __construct(public readonly string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Written in the traveller's language (User::preferredLocale).
        return (new MailMessage)
            ->subject(__(':code is your Sofor code', ['code' => $this->code]))
            ->markdown('mail.email-code', [
                'name' => MailName::first($notifiable->name ?? null),
                'code' => $this->code,
                'minutes' => EmailCodes::TTL_MINUTES,
            ]);
    }
}
