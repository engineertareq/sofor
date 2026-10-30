<?php

namespace App\Services;

use App\Exceptions\SmsWalletException;
use App\Models\TourRegistration;
use App\Models\User;

/**
 * A text from the organiser to somebody who booked: what is still due, and
 * the link to their booking page. Paid from the organiser's SMS wallet, like
 * every text a traveller chooses to send.
 */
class TourReminders
{
    /** Hours between two texts to the same booking. */
    public const WAIT_HOURS = 12;

    public function __construct(private readonly SmsWallet $wallet) {}

    /**
     * Kept to one plain SMS where it can: "Tk" instead of the taka sign, which
     * would make the whole text Unicode, and less detail until it fits.
     */
    public function text(TourRegistration $registration): string
    {
        $tour = mb_strimwidth($registration->tour->title, 0, 28, '..');
        $first = $registration->firstName();
        $url = $registration->url();
        $due = $registration->due();
        $tk = 'Tk '.number_format($due, $due == floor($due) ? 0 : 2);

        $candidates = $due > 0
            ? [
                "Sofor: Hi {$first}, {$tk} is due on your booking for {$tour}. Pay and see it here: {$url}",
                "Sofor: Hi {$first}, {$tk} is due on your booking. See it here: {$url}",
                "Sofor: {$tk} is due on your tour booking: {$url}",
            ]
            : [
                "Sofor: Hi {$first}, your booking for {$tour} is here: {$url}",
                "Sofor: Your tour booking is here: {$url}",
            ];

        foreach ($candidates as $text) {
            if (SmsWallet::parts($text) === 1) {
                return $text;
            }
        }

        return end($candidates);
    }

    /** @throws SmsWalletException with a message safe to show */
    public function send(User $organiser, TourRegistration $registration): void
    {
        if ($registration->reminded_at?->gt(now()->subHours(self::WAIT_HOURS))) {
            throw new SmsWalletException(__(':name was texted recently. You can text them again after :time.', [
                'name' => $registration->firstName(),
                'time' => $registration->reminded_at->addHours(self::WAIT_HOURS)->format('g:i a'),
            ]));
        }

        $this->wallet->send($organiser, $registration->phone, $this->text($registration), 'Tour reminder to '.$registration->name);

        $registration->forceFill(['reminded_at' => now()])->save();
    }
}
