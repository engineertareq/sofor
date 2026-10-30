<?php

namespace App\Services;

use App\Enums\TourBookingStatus;
use App\Models\Tour;
use App\Models\TourPayment;
use App\Models\TourRegistration;

/**
 * An organised tour's numbers: seats, what came in, what is still owed, what
 * it cost and what is left. The organiser's dashboard reads them from here,
 * and so does every participant's page when the organiser shares the account.
 */
class TourAccount
{
    /**
     * @return array{
     *     seats: int, capacity: ?int, seats_left: ?int, bookings: int, waitlisted: int, cancelled: int,
     *     expected: float, received: float, refunded: float, outstanding: float, refunds_owed: float,
     *     spent: float, balance: float, projected: float,
     *     pending: \Illuminate\Support\Collection, by_category: \Illuminate\Support\Collection, packages: \Illuminate\Support\Collection
     * }
     */
    public function summarize(Tour $tour): array
    {
        $tour->loadMissing(['packages', 'registrations.payments', 'registrations.package', 'expenses']);

        $registrations = $tour->registrations;
        $booked = $registrations->filter(fn (TourRegistration $r) => $r->isBooked());
        $confirmed = $registrations->flatMap->payments->where('status', TourPayment::STATUS_CONFIRMED);

        $collected = round((float) $confirmed->where('type', TourPayment::TYPE_PAYMENT)->sum('amount'), 2);
        $refunded = round((float) $confirmed->where('type', TourPayment::TYPE_REFUND)->sum('amount'), 2);
        $outstanding = round($booked->sum(fn (TourRegistration $r) => $r->due()), 2);
        $refundsOwed = round($registrations->sum(fn (TourRegistration $r) => $r->refundOwed()), 2);
        $spent = round((float) $tour->expenses->sum('amount'), 2);
        $seats = (int) $booked->sum('seats');

        return [
            'seats' => $seats,
            'capacity' => $tour->capacity,
            'seats_left' => $tour->capacity === null ? null : max(0, $tour->capacity - $seats),
            'bookings' => $booked->count(),
            'waitlisted' => $registrations->where('status', TourBookingStatus::Waitlisted)->count(),
            'cancelled' => $registrations->where('status', TourBookingStatus::Cancelled)->count(),
            // What the booked seats come to.
            'expected' => round($booked->sum(fn (TourRegistration $r) => (float) $r->total), 2),
            // In hand: payments less refunds.
            'received' => round($collected - $refunded, 2),
            'refunded' => $refunded,
            'outstanding' => $outstanding,
            'refunds_owed' => $refundsOwed,
            'spent' => $spent,
            'balance' => round($collected - $refunded - $spent, 2),
            // Once everyone pays and every refund owed is made.
            'projected' => round($collected - $refunded + $outstanding - $refundsOwed - $spent, 2),
            'pending' => $registrations
                ->flatMap(fn (TourRegistration $r) => $r->payments->where('status', TourPayment::STATUS_PENDING)->each(fn (TourPayment $p) => $p->setRelation('registration', $r)))
                ->sortBy('created_at')
                ->values(),
            'by_category' => $tour->expenses
                ->groupBy(fn ($expense) => $expense->category->value)
                ->map(fn ($group) => ['category' => $group->first()->category, 'amount' => round((float) $group->sum('amount'), 2)])
                ->sortByDesc('amount')
                ->values(),
            'packages' => $tour->packages->map(fn ($package) => [
                'package' => $package,
                'bookings' => (int) $booked->where('tour_package_id', $package->id)->sum('quantity'),
                'seats' => (int) $booked->where('tour_package_id', $package->id)->sum('seats'),
                'amount' => round((float) $booked->where('tour_package_id', $package->id)->sum(fn (TourRegistration $r) => (float) $r->total), 2),
            ]),
        ];
    }
}
