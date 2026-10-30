<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Tour;
use App\Models\TourPayment;
use App\Models\TourRegistration;
use App\Services\TourPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The organiser's side of the money: recording, confirming and refunding. */
class TourPaymentController extends Controller
{
    /** Money the organiser received (or a refund they sent): it counts at once. */
    public function store(Request $request, Tour $tour, TourRegistration $registration, TourPayments $payments): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $data = $request->validate([
            'type' => ['required', Rule::in([TourPayment::TYPE_PAYMENT, TourPayment::TYPE_REFUND])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::options()))],
            'reference' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        if ($data['type'] === TourPayment::TYPE_REFUND && (float) $data['amount'] > $registration->load('payments')->paid() + 0.009) {
            return back()->with('error', __('A refund cannot be more than :name has paid.', ['name' => $registration->name]));
        }

        $payments->record($registration, $data, $data['type']);

        return back()->with('status', $data['type'] === TourPayment::TYPE_REFUND
            ? __('Refund to :name recorded.', ['name' => $registration->name])
            : __('Payment from :name recorded.', ['name' => $registration->name]));
    }

    public function confirm(Tour $tour, TourPayment $payment, TourPayments $payments): RedirectResponse
    {
        $this->authorize('manage', $tour);
        $payments->confirm($payment);

        return back()->with('status', __('Payment confirmed.'));
    }

    public function reject(Tour $tour, TourPayment $payment, TourPayments $payments): RedirectResponse
    {
        $this->authorize('manage', $tour);
        $payments->reject($payment);

        return back()->with('status', __('Marked as not received.'));
    }

    public function destroy(Tour $tour, TourPayment $payment): RedirectResponse
    {
        $this->authorize('manage', $tour);
        $payment->delete();

        return back()->with('status', __('Payment removed.'));
    }
}
