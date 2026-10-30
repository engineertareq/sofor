<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\SmsWalletException;
use App\Models\Tour;
use App\Models\TourRegistration;
use App\Services\TourBooking;
use App\Services\TourPayments;
use App\Services\TourReminders;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The organiser's side of the bookings on a tour. */
class TourRegistrationController extends Controller
{
    /** A booking the organiser takes themselves: over the phone, or cash at the office. */
    public function store(Request $request, Tour $tour, TourBooking $booking, TourPayments $payments): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^\+8801[3-9]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'tour_package_id' => ['required', 'integer', Rule::exists('tour_packages', 'id')->where('tour_id', $tour->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:300'],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'method' => ['nullable', Rule::in(array_keys(PaymentMethod::options()))],
        ], ['phone.regex' => __('Enter a Bangladeshi mobile number.')]);

        $registration = DB::transaction(function () use ($tour, $data, $booking, $payments) {
            $registration = $booking->register($tour, $tour->packages()->findOrFail($data['tour_package_id']), $data, byOrganiser: true);

            if ((float) ($data['paid'] ?? 0) > 0) {
                $payments->record($registration, ['amount' => $data['paid'], 'method' => $data['method'] ?? PaymentMethod::Cash->value]);
            }

            return $registration;
        });

        return back()->with('status', $registration->isBooked()
            ? __(':name is booked.', ['name' => $registration->name])
            : __('The tour is full, so :name is on the waitlist.', ['name' => $registration->name]));
    }

    /** Give a waitlisted booking its seats, or cancel one. */
    public function update(Request $request, Tour $tour, TourRegistration $registration, TourBooking $booking): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $action = $request->validate(['action' => ['required', Rule::in(['book', 'cancel'])]])['action'];

        if ($action === 'cancel') {
            $booking->cancel($registration);

            return back()->with('status', $registration->load('payments')->paid() > 0
                ? __(':name is cancelled. Record the refund when you send it.', ['name' => $registration->name])
                : __(':name is cancelled.', ['name' => $registration->name]));
        }

        return $booking->book($registration)
            ? back()->with('status', __(':name is booked.', ['name' => $registration->name]))
            : back()->with('error', __('Not enough free seats for :name yet.', ['name' => $registration->name]));
    }

    /** A text with what is due and the link to their booking page, from the organiser's SMS wallet. */
    public function remind(Request $request, Tour $tour, TourRegistration $registration, TourReminders $reminders): RedirectResponse
    {
        $this->authorize('manage', $tour);

        try {
            $reminders->send($request->user(), $registration->load(['tour', 'payments']));
        } catch (SmsWalletException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __(':name was texted their booking link.', ['name' => $registration->name]));
    }

    /** Every booking as a spreadsheet: for the bus company, the hotel or the office accounts. */
    public function export(Tour $tour): StreamedResponse
    {
        $this->authorize('manage', $tour);

        $registrations = $tour->registrations()->with(['package', 'payments'])->oldest()->get();
        $filename = str($tour->title)->slug()->whenEmpty(fn () => str('tour'))->append('-bookings.csv')->toString();

        return response()->streamDownload(function () use ($registrations) {
            $out = fopen('php://output', 'w');
            // So Excel reads the Bangla names as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Phone', 'Email', 'Package', 'Quantity', 'Seats', 'Total', 'Paid', 'Due', 'Status', 'Booked on', 'Note']);

            foreach ($registrations as $registration) {
                fputcsv($out, [
                    $registration->name,
                    Phone::pretty($registration->phone),
                    $registration->email,
                    $registration->package?->name,
                    $registration->quantity,
                    $registration->seats,
                    number_format((float) $registration->total, 2, '.', ''),
                    number_format($registration->paid(), 2, '.', ''),
                    number_format($registration->due(), 2, '.', ''),
                    $registration->status->value,
                    $registration->created_at->format('Y-m-d H:i'),
                    $registration->note,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
