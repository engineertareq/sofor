<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\TourBookingStatus;
use App\Enums\TourStatus;
use App\Models\TourPayment;
use App\Models\TourRegistration;
use App\Services\TourAccount;
use App\Services\TourBooking;
use App\Services\TourPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Somebody's own booking page, /r/<token>: what they booked, what is still
 * due, where to pay it, and every payment they made. No account needed.
 * What they report paying waits for the organiser to confirm.
 */
class TourTicketController extends Controller
{
    public function show(string $token, TourAccount $account): View
    {
        $registration = $this->registration($token);
        $tour = $registration->tour;

        return view('tours.ticket', [
            'registration' => $registration,
            'tour' => $tour,
            // The organiser chose to show everyone what came in and what was spent.
            'account' => $tour->show_account ? $account->summarize($tour) : null,
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function paid(Request $request, string $token, TourPayments $payments): RedirectResponse
    {
        $registration = $this->registration($token);

        if (! $registration->isBooked() || ! $registration->tour->status->isActive()) {
            return back()->with('error', __('There is nothing to pay on this booking right now.'));
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::options()))],
            'reference' => ['nullable', 'string', 'max:80'],
            'sender' => ['nullable', 'string', 'max:30'],
        ], ['amount.min' => __('Enter the amount you paid.')]);

        if ($registration->payments->where('status', TourPayment::STATUS_PENDING)->count() >= TourPayments::MAX_PENDING) {
            return back()->with('error', __('You already have several payments waiting to be confirmed. Give them a little time.'));
        }

        $payments->report($registration, $data);

        return back()->with('status', __('Thanks! The organiser will confirm once they see the money.'));
    }

    /** Cancelling is theirs to do only while nothing is paid; after that the organiser handles the refund. */
    public function cancel(string $token, TourBooking $booking): RedirectResponse
    {
        $registration = $this->registration($token);

        if ($registration->status === TourBookingStatus::Cancelled || in_array($registration->tour->status, [TourStatus::Completed, TourStatus::Cancelled], true)) {
            return back()->with('error', __('This booking can no longer be cancelled here.'));
        }

        if ($registration->paid() > 0 || $registration->hasPendingPayment()) {
            return back()->with('error', __('You have paid towards this booking, so ask the organiser to cancel it and refund you.'));
        }

        $booking->cancel($registration, byParticipant: true);

        return back()->with('status', __('Your booking is cancelled.'));
    }

    private function registration(string $token): TourRegistration
    {
        return TourRegistration::query()
            ->where('token', $token)
            ->with(['tour.organiser.payoutMethods', 'package', 'payments'])
            ->firstOrFail();
    }
}
