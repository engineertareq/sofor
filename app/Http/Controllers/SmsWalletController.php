<?php

namespace App\Http\Controllers;

use App\Exceptions\BkashException;
use App\Models\SmsTopup;
use App\Services\BkashBrowserCheckout;
use App\Services\BkashService;
use App\Services\BkashSettlement;
use App\Services\SmsWallet;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The SMS wallet: prepaid credit for texts a traveller sends to their group,
 * topped up through Sofor's bKash merchant account. The payer is charged
 * exactly the top-up; bKash's fee is Sofor's to carry.
 */
class SmsWalletController extends Controller
{
    public function __construct(
        private readonly SmsWallet $wallet,
        private readonly BkashService $bkash,
        private readonly BkashBrowserCheckout $checkout,
        private readonly BkashSettlement $settlement,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('wallet.index', [
            'user' => $user,
            'price' => $this->wallet->price(),
            'messagesLeft' => $this->wallet->messagesLeft($user),
            'minimum' => $this->wallet->minimumTopup(),
            'maximum' => $this->wallet->maximumTopup(),
            'presets' => [10, 50, 100, 200, 500],
            'transactions' => $user->smsTransactions()->with('trip:id,title')->latest('id')->paginate(15),
            'bkashEnabled' => $this->bkash->canTakePayments(),
            'bkashMode' => config('bkash.mode', 'redirect'),
            'bkashScriptUrl' => config('bkash.script_url'),
            'sandbox' => (bool) config('bkash.sandbox', true),
        ]);
    }

    /** Redirect flow: create the payment and send the traveller to bKash's page. */
    public function start(Request $request): RedirectResponse
    {
        $amount = $this->validAmount($request);

        if (! $this->bkash->canTakePayments()) {
            return back()->with('error', __('bKash is not connected on this installation yet, so top-ups are paused.'));
        }

        $topup = $this->newTopup($request, $amount);

        try {
            $payment = $this->bkash->createPayment(
                amount: $this->bkash->payableAmount($amount),
                invoiceNumber: $topup->invoice,
                callbackUrl: route('wallet.callback'),
                payerReference: $request->user()->username,
            );
        } catch (BkashException $exception) {
            Log::error('bKash SMS top-up create failed', ['topup' => $topup->id, 'error' => $exception->getMessage()]);
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            return back()->with('error', __('bKash could not open the payment page. Please try again in a moment.').(app()->isLocal() ? ' '.Str::limit($exception->getMessage(), 140) : ''));
        }

        $topup->update(['payment_id' => $payment['paymentID']]);

        return redirect()->away($payment['bkashURL']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $paymentId = (string) $request->query('paymentID', '');

        // Only the traveller who started a top-up can finish it.
        $topup = $paymentId !== ''
            ? $request->user()->smsTopups()->where('payment_id', $paymentId)->latest('id')->first()
            : null;

        if ($topup === null) {
            Log::warning('bKash SMS top-up callback without a matching top-up', ['paymentID' => $paymentId]);

            return redirect()->route('wallet.index')->with('error', __('That bKash payment could not be matched to your wallet. Nothing was recorded.'));
        }

        if ($topup->isCompleted()) {
            return redirect()->route('wallet.index')->with('status', __('That top-up is already in your wallet.'));
        }

        if ($request->query('status') !== 'success') {
            $topup->update(['status' => SmsTopup::STATUS_CANCELLED]);

            return redirect()->route('wallet.index')->with('error', __('The bKash payment was cancelled, so nothing was added.'));
        }

        // Execute, and if that is unclear ask bKash where the payment really stands.
        $settled = $this->bkash->settle($paymentId);

        if (! $settled['completed']) {
            Log::error('bKash SMS top-up not completed', ['topup' => $topup->id, 'result' => $settled['result']]);
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            return redirect()->route('wallet.index')->with('error', __('bKash could not confirm the payment. If money left your account, contact Sofor support with the reference :payment_id.', ['payment_id' => $paymentId]));
        }

        $outcome = $this->settlement->topup($topup, $settled['result']);

        return redirect()->route('wallet.index')->with($outcome['ok'] ? 'status' : 'error', $outcome['message']);
    }

    /** Browser flow, step 1: create the payment for bKash's Checkout JS. */
    public function create(Request $request): JsonResponse
    {
        if (! $this->bkash->canTakePayments()) {
            return response()->json(['ok' => false, 'message' => __('bKash is not connected on this installation yet.')], 503);
        }

        try {
            $amount = $this->validAmount($request);
            $topup = $this->newTopup($request, $amount);
            $payment = $this->checkout->createForTopup($topup, $this->bkash->payableAmount($amount));
        } catch (ValidationException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->validator->errors()->first()], 422);
        } catch (BkashException $exception) {
            Log::error('bKash SMS top-up create failed', ['error' => $exception->getMessage()]);

            return response()->json(['ok' => false, 'message' => __('bKash could not start the payment. Please try again in a moment.')], 502);
        }

        return response()->json(['ok' => true] + $payment);
    }

    /** Browser flow, step 2: execute the authorised payment and credit the wallet. */
    public function execute(Request $request): JsonResponse
    {
        $data = $request->validate(['paymentID' => ['required', 'string', 'max:64']]);
        $topup = $request->user()->smsTopups()->where('payment_id', $data['paymentID'])->latest('id')->first();

        if ($topup === null) {
            return response()->json(['ok' => false, 'message' => __('That bKash payment could not be matched to your wallet. Nothing was recorded.')], 422);
        }

        if ($topup->isCompleted()) {
            return response()->json(['ok' => true, 'message' => __('That top-up is already in your wallet.'), 'redirect' => route('wallet.index')]);
        }

        $settled = $this->bkash->settle($data['paymentID']);

        if (! $settled['completed']) {
            Log::error('bKash SMS top-up not completed', ['topup' => $topup->id, 'result' => $settled['result']]);
            $topup->update(['status' => SmsTopup::STATUS_FAILED]);

            return response()->json(['ok' => false, 'message' => __('bKash could not confirm the payment. If money left your account, contact Sofor support with the reference :payment_id.', ['payment_id' => $data['paymentID']])], 422);
        }

        $outcome = $this->settlement->topup($topup, $settled['result']);

        return response()->json(['ok' => $outcome['ok'], 'message' => $outcome['message'], 'redirect' => route('wallet.index')], $outcome['ok'] ? 200 : 422);
    }

    /** @throws ValidationException */
    private function validAmount(Request $request): int
    {
        // bKash's checkout script sends the amount back as "50.00", so any
        // number is fine as long as it is a whole taka.
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$this->wallet->minimumTopup(), 'max:'.$this->wallet->maximumTopup(),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (floor((float) $value) !== (float) $value) {
                        $fail(__('Top up in whole taka.'));
                    }
                },
            ],
        ], [
            'amount.min' => __('The smallest top-up is :amount.', ['amount' => Money::format($this->wallet->minimumTopup())]),
            'amount.max' => __('The largest top-up is :amount.', ['amount' => Money::format($this->wallet->maximumTopup())]),
            'amount.numeric' => __('Top up in whole taka.'),
        ]);

        return (int) $data['amount'];
    }

    private function newTopup(Request $request, int $amount): SmsTopup
    {
        $topup = new SmsTopup([
            'amount' => $amount,
            'invoice' => 'SMS-'.Str::upper(Str::random(10)),
            'gateway' => 'bkash',
            'status' => SmsTopup::STATUS_PENDING,
        ]);
        $topup->user()->associate($request->user());
        $topup->save();

        return $topup;
    }
}
