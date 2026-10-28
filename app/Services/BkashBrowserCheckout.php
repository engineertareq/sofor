<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\SmsTopup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The browser half of the legacy bKash Checkout flow.
 *
 * bKash's Checkout JS renders the payment overlay itself, so instead of
 * redirecting the traveller away, the page asks Sofor to create the payment, hands
 * the payment id to the script, and asks Sofor to execute it once the traveller
 * has authorised it. Amounts are always recomputed here - the browser only gets
 * to say how much it would like to pay.
 */
class BkashBrowserCheckout
{
    public function __construct(private readonly BkashService $bkash) {}

    /**
     * Create a bKash payment for an SMS wallet top-up (validated by the caller).
     *
     * @return array{paymentID: string, base: float, charge: float, total: float, currency: string}
     */
    public function createForTopup(SmsTopup $topup, float $payable): array
    {
        try {
            $payment = $this->bkash->createPayment(
                amount: $payable,
                invoiceNumber: $topup->invoice,
                callbackUrl: route('wallet.callback'),
                payerReference: (string) $topup->user?->username,
            );
        } catch (\Throwable $exception) {
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            throw $exception;
        }

        $topup->update(['payment_id' => $payment['paymentID']]);

        return [
            'paymentID' => (string) $payment['paymentID'],
            'base' => (float) $topup->amount,
            'charge' => 0.0,
            'total' => $payable,
            'currency' => 'BDT',
        ];
    }

    /**
     * Create a bKash payment for a donation.
     *
     * @return array{paymentID: string, base: float, charge: float, total: float}
     */
    public function createForDonation(Request $request): array
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:20', 'max:100000'],
            'name' => ['nullable', 'string', 'max:80'],
            'contact' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:240'],
        ]);

        $breakdown = $this->bkash->breakdown((float) $data['amount']);

        // The donor pays the gift, nothing on top; Sofor absorbs the MFS fee.
        $donation = Donation::create([
            'name' => $data['name'] ?? null,
            'contact' => $data['contact'] ?? null,
            'message' => $data['message'] ?? null,
            'amount' => $breakdown['base'],
            'charge' => $breakdown['charge'],
            'total' => $breakdown['base'],
            'percent' => $breakdown['percent'],
            'invoice' => 'SUPPORT-'.Str::upper(Str::random(10)),
            'gateway' => 'bkash',
            'status' => Donation::STATUS_PENDING,
        ]);

        try {
            $payment = $this->bkash->createPayment(
                amount: $this->bkash->payableAmount($breakdown['base']),
                invoiceNumber: $donation->invoice,
                callbackUrl: route('support.callback'),
                payerReference: $donation->donorLabel(),
            );
        } catch (\Throwable $exception) {
            $donation->update(['status' => Donation::STATUS_FAILED]);

            throw $exception;
        }

        $donation->update(['payment_id' => $payment['paymentID']]);

        return [
            'paymentID' => (string) $payment['paymentID'],
            'base' => $breakdown['base'],
            'charge' => $breakdown['charge'],
            'total' => $breakdown['base'],
            'currency' => 'BDT',
        ];
    }

    /**
     * Execute an authorised donation and mark it complete.
     */
    public function executeForDonation(Request $request): JsonResponse
    {
        $data = $request->validate(['paymentID' => ['required', 'string', 'max:64']]);
        $paymentId = $data['paymentID'];

        $donation = Donation::query()->where('payment_id', $paymentId)->latest()->first();

        if ($donation === null) {
            Log::warning('bKash browser execute without a matching donation', ['paymentID' => $paymentId]);

            return response()->json(['ok' => false, 'message' => __('That bKash payment could not be matched to a support request. Nothing was recorded.')], 422);
        }

        if ($donation->isCompleted()) {
            return response()->json(['ok' => true, 'message' => __('This gift was already recorded.'), 'redirect' => route('support.thanks')]);
        }

        // Execute, and if that is unclear ask bKash where the payment really stands.
        $settled = $this->bkash->settle($paymentId);

        if (! $settled['completed']) {
            Log::error('bKash browser donation not completed', ['donation' => $donation->id, 'result' => $settled['result']]);
            $donation->update(['status' => Donation::STATUS_FAILED]);

            return response()->json([
                'ok' => false,
                'message' => __('bKash could not confirm the payment. If money left your account, contact Sofor support with the reference :payment_id.', ['payment_id' => $paymentId]),
                'paymentID' => $paymentId,
            ], 422);
        }

        $outcome = app(BkashSettlement::class)->donation($donation, $settled['result']);

        return response()->json([
            'ok' => $outcome['ok'],
            'message' => $outcome['message'],
            'redirect' => route('support.thanks'),
            'paymentID' => $paymentId,
        ], $outcome['ok'] ? 200 : 422);
    }
}
