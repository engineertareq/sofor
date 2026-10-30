<?php

namespace App\Services;

use App\Enums\TourBookingStatus;
use App\Models\Tour;
use App\Models\TourPackage;
use App\Models\TourRegistration;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Support\Phrase;
use Illuminate\Support\Facades\DB;

/**
 * Seats on an organised tour. Each change locks the tour's row first, so two
 * people booking at once cannot both take the last seat; a booking that does
 * not fit goes on the waitlist instead.
 */
class TourBooking
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, quantity: int, note?: ?string}  $details
     * @param  bool  $byOrganiser  Taken by the organiser (over the phone, at the office): no alert for them.
     */
    public function register(Tour $tour, TourPackage $package, array $details, ?User $user = null, bool $byOrganiser = false): TourRegistration
    {
        $registration = DB::transaction(function () use ($tour, $package, $details, $user) {
            $locked = Tour::query()->whereKey($tour->id)->lockForUpdate()->firstOrFail();
            $quantity = max(1, (int) $details['quantity']);
            $seats = $package->seats * $quantity;
            $left = $locked->seatsLeft();

            $registration = new TourRegistration([
                'name' => $details['name'],
                'phone' => $details['phone'],
                'email' => $details['email'] ?? null,
                'quantity' => $quantity,
                'seats' => $seats,
                'unit_price' => $package->price,
                'total' => round((float) $package->price * $quantity, 2),
                'note' => $details['note'] ?? null,
            ]);
            $registration->status = $left === null || $seats <= $left ? TourBookingStatus::Booked : TourBookingStatus::Waitlisted;
            $registration->tour()->associate($locked);
            $registration->package()->associate($package);
            $registration->user()->associate($user);
            $registration->save();

            return $registration;
        });

        if (! $byOrganiser) {
            $this->tellOrganiser($tour, $registration, $package);
        }

        return $registration;
    }

    /** Give a waitlisted (or cancelled) booking its seats, if enough are free. */
    public function book(TourRegistration $registration): bool
    {
        return DB::transaction(function () use ($registration) {
            $tour = Tour::query()->whereKey($registration->tour_id)->lockForUpdate()->firstOrFail();
            $left = $tour->seatsLeft();

            if ($registration->isBooked()) {
                return true;
            }

            if ($left !== null && $registration->seats > $left) {
                return false;
            }

            $registration->status = TourBookingStatus::Booked;
            $registration->cancelled_at = null;
            $registration->save();

            if ($registration->user) {
                $registration->user->notify(new TripNotification(
                    title: Phrase::make('You have a seat on :tour', ['tour' => $tour->title]),
                    body: Phrase::make('A seat freed up and your booking is in. See what to pay on your booking page.'),
                    url: $registration->url(),
                    tone: 'success',
                    icon: 'check',
                ));
            }

            return true;
        });
    }

    /** @param  bool  $byParticipant  Cancelled from their own booking page: the organiser hears of it. */
    public function cancel(TourRegistration $registration, bool $byParticipant = false): void
    {
        $registration->status = TourBookingStatus::Cancelled;
        $registration->cancelled_at = now();
        $registration->save();

        if ($byParticipant) {
            $tour = $registration->tour()->with('organiser')->first();

            $tour?->organiser?->notify(new TripNotification(
                title: Phrase::make(':name cancelled on :tour', ['name' => $registration->name, 'tour' => $tour->title]),
                body: Phrase::make(':seats seats are free again.', ['seats' => $registration->seats]),
                url: route('tours.show', $tour),
                tone: 'warning',
                icon: 'users',
            ));
        }
    }

    private function tellOrganiser(Tour $tour, TourRegistration $registration, TourPackage $package): void
    {
        $tour->loadMissing('organiser');

        $tour->organiser?->notify(new TripNotification(
            title: $registration->isBooked()
                ? Phrase::make('New booking on :tour', ['tour' => $tour->title])
                : Phrase::make('New waitlist booking on :tour', ['tour' => $tour->title]),
            body: Phrase::make(':name booked :package, :seats seats.', [
                'name' => $registration->name,
                'package' => $package->name,
                'seats' => $registration->seats,
            ]),
            url: route('tours.show', $tour),
            tone: $registration->isBooked() ? 'success' : 'warning',
            icon: 'users',
        ));
    }
}
