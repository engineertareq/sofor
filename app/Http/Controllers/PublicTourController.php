<?php

namespace App\Http\Controllers;

use App\Enums\TourBookingStatus;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Notifications\TourBooked;
use App\Services\TourBooking;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * A tour's public page, /t/<code>: what it is, what it costs, and the form
 * to book. No account needed - the link is what the organiser shares on
 * Facebook or Messenger. After booking, everyone has their own page
 * (TourTicketController).
 */
class PublicTourController extends Controller
{
    public function show(Request $request, string $code): View
    {
        $tour = $this->tour($request, $code);

        return view('tours.public', [
            'tour' => $tour,
            'seatsLeft' => $tour->seatsLeft(),
            'preview' => $tour->status === TourStatus::Draft,
        ]);
    }

    public function register(Request $request, string $code, TourBooking $booking): RedirectResponse
    {
        $tour = $this->tour($request, $code);

        if (! $tour->takesRegistrations()) {
            return back()->with('error', __('Registration for this tour is closed.'));
        }

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^\+8801[3-9]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'tour_package_id' => ['required', 'integer', Rule::exists('tour_packages', 'id')->where('tour_id', $tour->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:300'],
            // Left empty by people; filled in by bots.
            'website' => ['prohibited'],
        ], [
            'phone.regex' => __('Enter a Bangladeshi mobile number.'),
            'tour_package_id.required' => __('Choose a package.'),
        ]);

        // One booking per number, so a double tap or a second try cannot take seats twice.
        if ($tour->registrations()->where('phone', $data['phone'])->where('status', '!=', TourBookingStatus::Cancelled)->exists()) {
            return back()->withInput()->with('error', __('This number already has a booking on this tour. Open it from the link you got when you booked, or call the organiser.'));
        }

        $user = $request->user();
        $registration = $booking->register($tour, $tour->packages()->findOrFail($data['tour_package_id']), $data, $user?->isAdmin() ? null : $user);

        if (filled($data['email'] ?? null)) {
            try {
                Notification::route('mail', $data['email'])->notify(new TourBooked($registration));
            } catch (Throwable $e) {
                // The booking stands; the page itself shows the link to keep.
                report($e);
            }
        }

        return redirect()->route('tours.ticket', $registration->token)->with('status', $registration->isBooked()
            ? __('You are booked! Keep this page: it shows what to pay and every payment you make.')
            : __('The tour is full right now, so you are on the waitlist. Keep this page to see if a seat frees up.'));
    }

    /** A draft is only shown to its organiser, as a preview. */
    private function tour(Request $request, string $code): Tour
    {
        $tour = Tour::query()->where('code', $code)->with(['packages', 'organiser.payoutMethods'])->firstOrFail();

        abort_if($tour->status === TourStatus::Draft && $request->user()?->id !== $tour->user_id, 404);

        return $tour;
    }
}
