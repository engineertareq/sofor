<?php

namespace App\Http\Controllers;

use App\Exceptions\BkashException;
use App\Models\Donation;
use App\Services\BkashBrowserCheckout;
use App\Services\BkashService;
use App\Services\BkashSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Voluntary support for the project. Donations are collected through the same
 * bKash Checkout integration the trip ledger uses. The donor is charged exactly
 * the amount they chose - bKash's MFS fee is absorbed by Sofor and recorded on
 * the donation so the numbers still add up.
 */
class SupportController extends Controller
{
    private const MIN_AMOUNT = 20;

    private const MAX_AMOUNT = 100000;

    private const DEFAULT_AMOUNT = 250;

    public function __construct(
        private readonly BkashService $bkash,
        private readonly BkashBrowserCheckout $checkout,
    ) {}

    public function index(): View
    {
        $completed = Donation::query()->completed();

        // A link such as /support?amount=500 pre-selects that gift, as long as it
        // stays inside the limits the same form validates against.
        $requested = (float) request()->input('amount', self::DEFAULT_AMOUNT);

        return view('support.index', [
            'presets' => Donation::SUGGESTED,
            'initialAmount' => $requested >= self::MIN_AMOUNT && $requested <= self::MAX_AMOUNT
                ? $requested
                : self::DEFAULT_AMOUNT,
            'minAmount' => self::MIN_AMOUNT,
            'maxAmount' => self::MAX_AMOUNT,
            'bkashEnabled' => $this->bkash->canTakePayments(),
            'bkashMode' => config('bkash.mode', 'redirect'),
            'bkashScriptUrl' => config('bkash.script_url'),
            'chargePercent' => $this->bkash->mfsChargePercent(),
            'sandbox' => (bool) config('bkash.sandbox', true),
            'supporterCount' => (clone $completed)->count(),
            'raised' => (float) (clone $completed)->sum('amount'),
            'recent' => (clone $completed)->latest()->limit(6)->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.self::MIN_AMOUNT, 'max:'.self::MAX_AMOUNT],
            'name' => ['nullable', 'string', 'max:80'],
            'contact' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:240'],
        ]);

        if (! $this->bkash->canTakePayments()) {
            return back()->withInput()->with('error', __('bKash is not connected on this installation yet, so online support is paused. Please call us if you would like to help.'));
        }

        $breakdown = $this->bkash->breakdown((float) $data['amount']);

        // `amount` is the gift, `total` is what bKash charges the donor. We ask
        // for the gift only, so the fee never lands on the person giving.
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
        } catch (BkashException $exception) {
            Log::error('bKash donation create failed', ['donation' => $donation->id, 'error' => $exception->getMessage()]);

            $donation->update(['status' => Donation::STATUS_FAILED]);

            $reason = app()->isLocal() ? ' '.Str::limit($exception->getMessage(), 140) : '';

            return back()->withInput()->with('error', __('bKash could not open the payment page. Please try again in a moment.').$reason);
        }

        $donation->update(['payment_id' => $payment['paymentID']]);

        // Off to the hosted bKash checkout page.
        return redirect()->away($payment['bkashURL']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $paymentId = (string) $request->query('paymentID', '');
        $status = (string) $request->query('status', '');

        $donation = $paymentId !== ''
            ? Donation::query()->where('payment_id', $paymentId)->latest()->first()
            : null;

        if ($donation === null) {
            Log::warning('bKash donation callback without a matching donation', ['paymentID' => $paymentId]);

            return redirect()->route('support.index')
                ->with('error', __('That bKash payment could not be matched to a support request. Nothing was recorded.'));
        }

        if ($donation->isCompleted()) {
            return redirect()->route('support.thanks');
        }

        if ($status !== 'success') {
            $donation->update(['status' => Donation::STATUS_CANCELLED]);

            return redirect()->route('support.index')
                ->with('error', __('The bKash payment was cancelled, so no gift was recorded. You can try again any time.'));
        }

        // Execute, and if that is unclear ask bKash where the payment really stands.
        $settled = $this->bkash->settle($paymentId);

        if (! $settled['completed']) {
            Log::error('bKash donation not completed', ['donation' => $donation->id, 'result' => $settled['result']]);
            $donation->update(['status' => Donation::STATUS_FAILED]);

            return redirect()->route('support.index')
                ->with('error', __('bKash could not confirm the payment. If money left your account, contact Sofor support with the reference :payment_id.', ['payment_id' => $paymentId]));
        }

        $outcome = app(BkashSettlement::class)->donation($donation, $settled['result']);

        return $outcome['ok']
            ? redirect()->route('support.thanks')->with('status', $outcome['message'])
            : redirect()->route('support.index')->with('error', $outcome['message']);
    }

    public function thanks(): View
    {
        return view('support.thanks', [
            'donation' => Donation::query()->completed()->latest()->first(),
        ]);
    }

    /**
     * JSON step 1 of the browser checkout: create the payment and hand the id to
     * bKash's Checkout JS, which renders the payment overlay itself.
     */
    public function create(Request $request): JsonResponse
    {
        if (! $this->bkash->canTakePayments()) {
            return response()->json(['ok' => false, 'message' => __('bKash is not connected on this installation yet.')], 503);
        }

        try {
            $payment = $this->checkout->createForDonation($request);
        } catch (ValidationException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->validator->errors()->first()], 422);
        } catch (BkashException $exception) {
            Log::error('bKash donation create failed', ['error' => $exception->getMessage()]);

            return response()->json(['ok' => false, 'message' => __('bKash could not start the payment. Please try again in a moment.').(app()->isLocal() ? ' '.$exception->getMessage() : '')], 502);
        }

        return response()->json(['ok' => true] + $payment);
    }

    /**
     * JSON step 2: the traveller authorised the payment, so execute it and record
     * the gift.
     */
    public function execute(Request $request): JsonResponse
    {
        if (! $this->bkash->canTakePayments()) {
            return response()->json(['ok' => false, 'message' => __('bKash is not connected on this installation yet.')], 503);
        }

        try {
            return $this->checkout->executeForDonation($request);
        } catch (ValidationException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->validator->errors()->first()], 422);
        }
    }
}
