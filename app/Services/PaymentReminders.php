<?php

namespace App\Services;

use App\Support\Phrase;
use App\Exceptions\SmsWalletException;
use App\Models\PaymentReminder;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\PaymentDueReminder;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * "You still owe" nudges. Sofor sends them, so the friend who is owed does
 * not have to be the one who asks. In-app and email are free; a text is
 * optional and paid from the sender's SMS wallet.
 */
class PaymentReminders
{
    /** A traveller is reminded at most this often by hand. */
    public const COOLDOWN_HOURS = 20;

    /** Automatic reminders after a trip ends: two days in, then weekly, this many at most. */
    public const AUTOMATIC_MAX = 4;

    public function __construct(
        private readonly TripLedger $ledger,
        private readonly TripActivityLogger $logger,
        private readonly SmsWallet $wallet,
    ) {}

    /**
     * What a traveller owes right now, to whom.
     *
     * @return list<array{to: TripMember, amount: float}>
     */
    public function owes(Trip $trip, TripMember $debtor): array
    {
        return collect($this->ledger->summarize($trip)['suggestions'])
            ->filter(fn (array $suggestion) => $suggestion['from_id'] === $debtor->id)
            ->map(fn (array $suggestion) => ['to' => $suggestion['to'], 'amount' => (float) $suggestion['amount']])
            ->values()
            ->all();
    }

    /** Hours until this traveller may be reminded by hand again; 0 when they may. */
    public function cooldown(TripMember $debtor): int
    {
        $last = PaymentReminder::query()->where('trip_member_id', $debtor->id)->where('automatic', false)->latest('id')->first();

        return $last === null ? 0 : max(0, self::COOLDOWN_HOURS - (int) $last->created_at->diffInHours(now()));
    }

    /**
     * Remind one traveller. $payer covers the text, if one is sent.
     *
     * @return array{ok: bool, channels: list<string>, message: string}
     */
    public function remind(Trip $trip, TripMember $debtor, ?User $sender, bool $sms = false, bool $automatic = false, ?User $payer = null): array
    {
        $owes = $this->owes($trip, $debtor);
        $total = round(array_sum(array_column($owes, 'amount')), 2);

        if ($total <= 0.009) {
            return ['ok' => false, 'channels' => [], 'message' => __(':debtor does not owe anything right now.', ['debtor' => $debtor->name])];
        }

        if (! $automatic && ($wait = $this->cooldown($debtor)) > 0) {
            return ['ok' => false, 'channels' => [], 'message' => $wait === 1
                ? __(':debtor was reminded recently. You can remind them again in 1 hour.', ['debtor' => $debtor->name])
                : __(':debtor was reminded recently. You can remind them again in :hours hours.', ['debtor' => $debtor->name, 'hours' => $wait])];
        }

        $debtor->loadMissing('user');
        $payUrl = $debtor->payUrl();
        $amount = Phrase::money($total, $trip->currency);
        $channels = [];
        $problems = [];

        if ($debtor->user !== null) {
            $this->logger->notifyUser($debtor->user, $trip, Phrase::make('Reminder: :amount open on :trip', ['amount' => $amount, 'trip' => $trip->title]),
                Phrase::make('Pay :people directly, then tap "I paid".', ['people' => $this->peopleList($owes, $trip->currency)]), 'warning', 'bell');
            $channels[] = 'app';
        }

        if (($email = $debtor->mailableEmail()) !== null) {
            try {
                $debtor->user !== null
                    ? $debtor->user->notify(new PaymentDueReminder($trip, $debtor, $owes, $payUrl))
                    : Notification::route('mail', $email)->notify(new PaymentDueReminder($trip, $debtor, $owes, $payUrl));
                $channels[] = 'email';
            } catch (Throwable $e) {
                report($e);
                $problems[] = __('the email could not be sent');
            }
        }

        if ($sms) {
            $phone = $debtor->textablePhone();
            $payer ??= $sender;

            if ($phone === null) {
                $problems[] = __(':debtor has no mobile number for a text', ['debtor' => $debtor->name]);
            } elseif ($payer !== null) {
                try {
                    $this->wallet->send($payer, $phone, $this->text($trip, $debtor, $total, $owes, $payUrl), 'Payment reminder to '.$debtor->name, $trip);
                    $channels[] = 'sms';
                } catch (SmsWalletException $e) {
                    $problems[] = lcfirst(rtrim($e->getMessage(), '.'));
                }
            }
        }

        if ($channels === []) {
            return ['ok' => false, 'channels' => [], 'message' => $problems !== []
                ? __('Could not reach :debtor: :problems.', ['debtor' => $debtor->name, 'problems' => implode('; ', $problems)])
                : __('Could not reach :debtor - they have no account, email or phone on this trip.', ['debtor' => $debtor->name])];
        }

        PaymentReminder::create([
            'trip_id' => $trip->id,
            'trip_member_id' => $debtor->id,
            'sent_by' => $sender?->id,
            'amount' => $total,
            'channels' => implode(',', $channels),
            'automatic' => $automatic,
        ]);

        if (! $automatic) {
            $this->logger->log($trip, 'reminder_sent', Phrase::make(':name reminded :debtor about :amount', ['name' => $sender?->name ?? 'Sofor', 'debtor' => $debtor->name, 'amount' => $amount]), $sender, $debtor);
        }

        $how = ['app' => __('in the app'), 'email' => __('by email'), 'sms' => __('by text')];
        $message = __(':debtor was reminded :how.', ['debtor' => $debtor->name, 'how' => collect($channels)->map(fn ($c) => $how[$c])->join(', ', ' '.__('and').' ')]);

        return ['ok' => true, 'channels' => $channels, 'message' => $problems !== [] ? $message.' '.__('Note: :problems.', ['problems' => implode('; ', $problems)]) : $message];
    }

    /**
     * The reminder text. It tries to stay one plain SMS (160 characters, no
     * Unicode) - "Tk" instead of the taka sign, which alone would force a
     * Unicode text - dropping detail until it fits.
     *
     * @param  list<array{to: TripMember, amount: float}>  $owes
     */
    public function text(Trip $trip, TripMember $debtor, float $total, array $owes, string $payUrl): string
    {
        $tk = 'Tk '.number_format($total, $total == floor($total) ? 0 : 2);
        $to = count($owes) === 1 ? ' to '.$owes[0]['to']->firstName() : '';
        $title = mb_strimwidth($trip->title, 0, 28, '..');

        $candidates = [
            "Sofor: Hi {$debtor->firstName()}, you owe {$tk}{$to} for {$title}. Where to pay: {$payUrl}",
            "Sofor: Hi {$debtor->firstName()}, you owe {$tk}{$to}. Where to pay: {$payUrl}",
            "Sofor: You owe {$tk} for a trip. Where to pay: {$payUrl}",
        ];

        foreach ($candidates as $text) {
            if (SmsWallet::parts($text) === 1) {
                return $text;
            }
        }

        return end($candidates);
    }

    /**
     * "Rifat ৳300, Asha ৳200" as a sentence part whose amounts are written
     * for each reader.
     *
     * @param  list<array{to: TripMember, amount: float}>  $owes
     */
    private function peopleList(array $owes, string $currency): Phrase
    {
        $parts = [];

        foreach (array_values($owes) as $i => $row) {
            $parts['p'.$i] = Phrase::make(':name :amount', ['name' => $row['to']->name, 'amount' => Phrase::money($row['amount'], $currency)]);
        }

        return Phrase::make(implode(', ', array_map(fn (string $key) => ':'.$key, array_keys($parts))), $parts);
    }
}
