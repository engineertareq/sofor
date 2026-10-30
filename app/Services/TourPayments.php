<?php

namespace App\Services;

use App\Models\TourPayment;
use App\Models\TourRegistration;
use App\Notifications\TripNotification;
use App\Support\Phrase;

/**
 * Money on an organised tour. It goes straight from the participant to the
 * organiser; Sofor keeps the record. What a participant reports waits for the
 * organiser to find it in their own account and confirm it; what the
 * organiser records themselves (cash at the office, a refund) counts at once.
 */
class TourPayments
{
    /** At most this many reports waiting from one booking, so a page cannot be used to spam. */
    public const MAX_PENDING = 5;

    /**
     * @param  array{amount: float|string, method: string, reference?: ?string, sender?: ?string}  $data
     */
    public function report(TourRegistration $registration, array $data): TourPayment
    {
        $payment = $this->make($registration, $data, TourPayment::TYPE_PAYMENT);
        $payment->status = TourPayment::STATUS_PENDING;
        $payment->save();

        $tour = $registration->tour;
        $tour->loadMissing('organiser');

        $tour->organiser?->notify(new TripNotification(
            title: Phrase::make('Payment to confirm on :tour', ['tour' => $tour->title]),
            body: Phrase::make(':name says they paid :amount by :method.', [
                'name' => $registration->name,
                'amount' => Phrase::money($payment->amount, $tour->currency),
                'method' => $payment->method->label(),
            ]),
            url: route('tours.show', $tour),
            tone: 'warning',
            icon: 'wallet',
        ));

        return $payment;
    }

    /**
     * @param  array{amount: float|string, method: string, reference?: ?string, sender?: ?string, note?: ?string, paid_on?: ?string}  $data
     */
    public function record(TourRegistration $registration, array $data, string $type = TourPayment::TYPE_PAYMENT): TourPayment
    {
        $payment = $this->make($registration, $data, $type);
        $payment->status = TourPayment::STATUS_CONFIRMED;
        $payment->confirmed_at = now();
        $payment->save();

        return $payment;
    }

    public function confirm(TourPayment $payment): void
    {
        $payment->status = TourPayment::STATUS_CONFIRMED;
        $payment->confirmed_at = now();
        $payment->save();

        $this->tellParticipant($payment, Phrase::make('Your payment for :tour is confirmed', ['tour' => $payment->tour->title]), 'success');
    }

    public function reject(TourPayment $payment): void
    {
        $payment->status = TourPayment::STATUS_REJECTED;
        $payment->save();

        $this->tellParticipant($payment, Phrase::make('The organiser of :tour could not find your payment', ['tour' => $payment->tour->title]), 'warning');
    }

    private function make(TourRegistration $registration, array $data, string $type): TourPayment
    {
        $payment = new TourPayment([
            'type' => $type,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'sender' => $data['sender'] ?? null,
            'note' => $data['note'] ?? null,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
        ]);
        $payment->tour()->associate($registration->tour_id);
        $payment->registration()->associate($registration);

        return $payment;
    }

    /** Only a participant who booked while signed in has somewhere to be told. */
    private function tellParticipant(TourPayment $payment, Phrase $title, string $tone): void
    {
        $registration = $payment->registration()->with('user')->first();

        $registration?->user?->notify(new TripNotification(
            title: $title,
            body: Phrase::make(':amount by :method.', [
                'amount' => Phrase::money($payment->amount, $payment->tour->currency),
                'method' => $payment->method->label(),
            ]),
            url: $registration->url(),
            tone: $tone,
            icon: 'wallet',
        ));
    }
}
