<?php

namespace App\Notifications;

use App\Models\TourRegistration;
use App\Support\MailName;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your booking for <tour>": sent to whoever booked on a tour's public page
 * and gave an email, so the link to their booking page is never lost.
 */
class TourBooked extends Notification
{
    use Queueable;

    public function __construct(public TourRegistration $registration) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tour = $this->registration->tour;

        return (new MailMessage)
            ->subject(__('Your booking for :tour', ['tour' => $tour->title]))
            ->greeting(__('Hi :name,', ['name' => MailName::first($this->registration->name)]))
            ->line($this->registration->isBooked()
                ? __('Your seats on :tour are booked.', ['tour' => $tour->title])
                : __(':tour is full right now, so you are on the waitlist. You will get a seat if one frees up.', ['tour' => $tour->title]))
            ->line(__('Your booking page shows what to pay, where to pay it and every payment you make. Keep this email to find it again.'))
            ->action(__('Open my booking'), $this->registration->url())
            ->salutation(__('Happy travels,')."  \n".__('The Sofor team'));
    }
}
