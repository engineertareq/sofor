<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Models\TripMember;
use App\Support\MailName;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You still owe" by email. Sent by Sofor, not by the friend who is owed, so
 * nobody has to be the one who asks - and it carries the pay link, so paying
 * is one tap away.
 */
class PaymentDueReminder extends Notification
{
    /**
     * @param  list<array{to: TripMember, amount: float}>  $owes
     */
    public function __construct(
        public readonly Trip $trip,
        public readonly TripMember $debtor,
        public readonly array $owes,
        public readonly string $payUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = array_sum(array_column($this->owes, 'amount'));
        $currency = $this->trip->currency;

        $title = MailName::text($this->trip->title);

        // Written in the recipient's language (User::preferredLocale).
        $mail = (new MailMessage)
            ->subject(__('A friendly reminder about :trip', ['trip' => $title]))
            ->greeting(__('Hi :name,', ['name' => MailName::first($this->debtor->name)]))
            ->line(__('Your share from **:trip** still has :amount open:', ['trip' => $title, 'amount' => Money::format($total, $currency)]));

        foreach ($this->owes as $row) {
            $mail->line('- '.__(':amount to :name', ['amount' => Money::format($row['amount'], $currency), 'name' => MailName::text($row['to']->name, 40)]));
        }

        return $mail
            ->action(__('See where to pay'), $this->payUrl)
            ->line(__('The link shows each person\'s bKash, Nagad or bank details. Once you have paid, tap "I paid" so they can confirm.'))
            ->line(__('Already paid? Just record it from the link and this reminder stops.'))
            ->salutation(__('Happy travels,')."  \n".__('The Sofor team'));
    }
}
