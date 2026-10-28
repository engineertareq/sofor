<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use App\Support\MailName;
use Illuminate\Notifications\Messages\MailMessage;

/** "Reset your password". Laravel's reset token (route password.reset), in Sofor's words. */
class ResetPasswordLink extends ResetPassword
{
    /** @param  \App\Models\User  $notifiable */
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        // Written in the traveller's language (User::preferredLocale).
        return (new MailMessage)
            ->subject(__('Reset your Sofor password'))
            ->greeting(__('Hi :name,', ['name' => MailName::first($notifiable->name)]))
            ->line(__('Somebody - hopefully you - asked to reset the password for your Sofor account.'))
            ->action(__('Choose a new password'), $this->resetUrl($notifiable))
            ->line(__('The button works for :minutes minutes and only once.', ['minutes' => $minutes]))
            ->line(__('Did not ask for this? Ignore this email and your password stays exactly as it is.'))
            ->salutation(__('Happy travels,')."  \n".__('The Sofor team'));
    }
}
