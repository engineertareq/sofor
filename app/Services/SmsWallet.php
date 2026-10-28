<?php

namespace App\Services;

use App\Exceptions\SmsException;
use App\Exceptions\SmsWalletException;
use App\Models\SmsTopup;
use App\Models\SmsTransaction;
use App\Models\Trip;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid SMS credit. Texts a traveller sends to their group - payment
 * reminders, pay links, check-ins for family - are paid from their own
 * balance, so the cost lands on whoever chose to text. (Verification and
 * password codes are Sofor's and stay free.)
 *
 * The price is per SMS part: a long text, or one in Bangla (70 characters a
 * part instead of 160), costs more than one.
 */
class SmsWallet
{
    public function __construct(private readonly SmsService $sms) {}

    public function price(): float
    {
        return (float) config('services.sms.price', 0.45);
    }

    public function minimumTopup(): int
    {
        return (int) config('services.sms.min_topup', 10);
    }

    public function maximumTopup(): int
    {
        return (int) config('services.sms.max_topup', 5000);
    }

    /**
     * How many SMS parts a text takes. Plain text fits 160 characters (153 a
     * part once split); anything outside it - Bangla, emoji - goes as Unicode,
     * 70 characters (67 a part).
     */
    public static function parts(string $message): int
    {
        $unicode = preg_match('/[^\x0A\x0D\x20-\x7E]/', $message) === 1;
        $length = $unicode ? mb_strlen($message) : strlen($message) + preg_match_all('/[\^{}\\\\\[\]~|]/', $message);

        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    public function cost(string $message): float
    {
        return round(self::parts($message) * $this->price(), 2);
    }

    /** How many plain texts the balance still pays for. */
    public function messagesLeft(User $user): int
    {
        return (int) floor((float) $user->sms_balance / max(0.01, $this->price()));
    }

    /**
     * Text somebody and charge it to $payer. The balance is taken first, so two
     * texts at once cannot both spend the same taka; if the gateway refuses the
     * text, the charge is refunded.
     *
     * @throws SmsWalletException
     */
    public function send(User $payer, string $phone, string $message, string $description, ?Trip $trip = null): SmsTransaction
    {
        $parts = self::parts($message);
        $cost = round($parts * $this->price(), 2);
        $recipient = Phone::pretty($phone);

        $charge = DB::transaction(function () use ($payer, $cost, $parts, $description, $recipient, $trip) {
            $locked = User::query()->whereKey($payer->id)->lockForUpdate()->firstOrFail();

            if ((float) $locked->sms_balance + 0.0001 < $cost) {
                throw SmsWalletException::insufficient($cost, (float) $locked->sms_balance);
            }

            return $this->move($locked, SmsTransaction::TYPE_CHARGE, -$cost, $description, $parts, $recipient, $trip);
        });

        try {
            $this->sms->send($phone, $message);
        } catch (SmsException $e) {
            report($e);

            DB::transaction(function () use ($payer, $cost, $parts, $recipient, $trip) {
                $locked = User::query()->whereKey($payer->id)->lockForUpdate()->firstOrFail();
                $this->move($locked, SmsTransaction::TYPE_REFUND, $cost, 'Refund: the text to '.$recipient.' could not be sent', $parts, $recipient, $trip);
            });

            throw new SmsWalletException(__('The text to :phone could not be sent just now, so nothing was charged. Please try again in a minute.', ['phone' => $recipient]));
        }

        $payer->refresh();

        return $charge;
    }

    /** Credit a completed top-up, once - a repeated bKash callback must not pay twice. */
    public function credit(SmsTopup $topup): void
    {
        DB::transaction(function () use ($topup) {
            $locked = SmsTopup::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if (SmsTransaction::query()->where('sms_topup_id', $locked->id)->exists()) {
                return;
            }

            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $this->move($user, SmsTransaction::TYPE_TOPUP, (float) $locked->amount, 'Top-up through bKash', topup: $locked);
        });
    }

    private function move(User $user, string $type, float $amount, string $description, int $messages = 0, ?string $recipient = null, ?Trip $trip = null, ?SmsTopup $topup = null): SmsTransaction
    {
        $balance = round((float) $user->sms_balance + $amount, 2);
        $user->forceFill(['sms_balance' => $balance])->save();

        $transaction = new SmsTransaction([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balance,
            'messages' => $messages,
            'description' => mb_substr($description, 0, 160),
            'recipient' => $recipient,
            'trip_id' => $trip?->id,
            'sms_topup_id' => $topup?->id,
        ]);
        $transaction->user()->associate($user);
        $transaction->save();

        return $transaction;
    }
}
