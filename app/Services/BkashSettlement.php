<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\SmsTopup;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Books a bKash payment that bKash says is completed (BkashService::settle or a
 * Query Payment): an SMS wallet top-up is credited, a gift is recorded. Shared
 * by the redirect and browser flows and by `bkash:reconcile`, so all of them
 * apply the same checks - the amount asked for, a transaction id not seen
 * before - and nothing is ever counted twice.
 */
class BkashSettlement
{
    public function __construct(
        private readonly BkashService $bkash,
        private readonly SmsWallet $wallet,
    ) {}

    /**
     * @param  array<string, mixed>  $result  bKash's execute or query response, completed
     * @return array{ok: bool, message: string}
     */
    public function topup(SmsTopup $topup, array $result): array
    {
        if ($topup->isCompleted()) {
            return ['ok' => true, 'message' => __('That top-up is already in your wallet.')];
        }

        if (! BkashService::isCompleted($result)) {
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            return ['ok' => false, 'message' => __('The bKash payment did not complete, so nothing was added.')];
        }

        if (! $this->bkash->confirmsAmount($result, $this->bkash->payableAmount((float) $topup->amount))) {
            Log::error('bKash SMS top-up amount mismatch', ['topup' => $topup->id, 'result' => $result]);
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            return ['ok' => false, 'message' => __('bKash confirmed a different amount than this top-up, so nothing was added. Contact Sofor support with the reference :payment_id.', ['payment_id' => $topup->payment_id])];
        }

        $trxId = (string) ($result['trxID'] ?? '');

        if ($trxId !== '' && SmsTopup::query()->where('trx_id', $trxId)->whereKeyNot($topup->id)->exists()) {
            return ['ok' => true, 'message' => __('That top-up is already in your wallet.')];
        }

        $topup->forceFill(['trx_id' => $trxId !== '' ? $trxId : null, 'status' => SmsTopup::STATUS_COMPLETED])->save();
        $this->wallet->credit($topup);

        return ['ok' => true, 'message' => __(':amount added to your SMS wallet. That is about :refresh texts.', [
            'amount' => Money::format($topup->amount),
            'refresh' => $this->wallet->messagesLeft($topup->user->refresh()),
        ])];
    }

    /**
     * @param  array<string, mixed>  $result  bKash's execute or query response, completed
     * @return array{ok: bool, message: string}
     */
    public function donation(Donation $donation, array $result): array
    {
        if ($donation->isCompleted()) {
            return ['ok' => true, 'message' => __('This gift was already recorded.')];
        }

        if (! BkashService::isCompleted($result)) {
            $donation->update(['status' => Donation::STATUS_FAILED]);

            return ['ok' => false, 'message' => __('The bKash payment did not complete, so nothing was recorded.')];
        }

        if (! $this->bkash->confirmsAmount($result, $this->bkash->payableAmount((float) $donation->total))) {
            Log::error('bKash donation amount mismatch', ['donation' => $donation->id, 'result' => $result]);
            $donation->update(['status' => Donation::STATUS_FAILED]);

            return ['ok' => false, 'message' => __('bKash confirmed a different amount than this gift, so nothing was recorded. Contact Sofor support with the reference :payment_id.', ['payment_id' => $donation->payment_id])];
        }

        $trxId = (string) ($result['trxID'] ?? '');

        // bKash sometimes repeats a callback; never count the same money twice.
        if ($trxId !== '' && Donation::query()->where('trx_id', $trxId)->whereKeyNot($donation->id)->exists()) {
            return ['ok' => true, 'message' => __('This gift was already recorded.')];
        }

        $donation->update(['trx_id' => $trxId !== '' ? $trxId : null, 'status' => Donation::STATUS_COMPLETED]);

        return ['ok' => true, 'message' => __('Thank you. Your :amount gift is in and it keeps Sofor free for every traveller.', ['amount' => Money::format($donation->amount)])];
    }
}
