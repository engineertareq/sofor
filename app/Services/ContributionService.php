<?php

namespace App\Services;

use App\Support\Phrase;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Money;

/**
 * Single place where money paid into a trip is written down, no matter whether a
 * traveller recorded it by hand or paid through bKash. Keeps the recipient
 * resolution, the trip timeline and the notifications in one consistent shape.
 */
class ContributionService
{
    public function __construct(
        private readonly TripLedger $ledger,
        private readonly TripActivityLogger $logger,
    ) {}

    /**
     * Works out who should receive a contribution that was not aimed at anybody
     * in particular, so the money always clears a real due.
     *
     * @param  \Illuminate\Support\Collection<int, TripMember>  $members
     */
    public function resolveRecipient(Trip $trip, TripMember $from, $members): TripMember
    {
        // 1. Somebody who owes money pays down their own due.
        $suggestion = $this->ledger->suggestionFor($trip, $from->id);

        if ($suggestion !== null && (int) $suggestion['to_id'] !== $from->id) {
            $resolved = $members->firstWhere('id', $suggestion['to_id']);

            if ($resolved instanceof TripMember) {
                return $resolved;
            }
        }

        // 2. Somebody who is already square hands the money to the traveller who
        //    has fronted the most, so the group levels out.
        $creditor = $this->ledger->biggestCreditor($trip, $from->id);

        if ($creditor !== null) {
            return $creditor['member'];
        }

        // 3. Everybody is square: park it with the trip leader, or with any other
        //    traveller if the payer happens to be the leader.
        $leader = $members->firstWhere('role', 'owner');

        if ($leader instanceof TripMember && $leader->id !== $from->id) {
            return $leader;
        }

        return $members->first(fn (TripMember $member) => $member->id !== $from->id) ?? $from;
    }

    /**
     * Writes a contribution into the ledger: who paid, who received it, how, and
     * the transaction id they paid with.
     *
     * Money moves person to person, so Sofor cannot see it. A payment the
     * receiver (or the leader) writes down is confirmed at once; one reported
     * by anybody else - the payer, or somebody paying through a pay link with
     * no account ($actor null) - waits for the receiver to confirm it.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Trip $trip, TripMember $from, ?TripMember $to, ?User $actor, array $data): Settlement
    {
        $members = $trip->members()->get();

        if ($to === null || $to->id === $from->id) {
            $to = $this->resolveRecipient($trip, $from, $members);
        }

        $confirmed = $actor !== null && ($actor->isAdmin() || $trip->user_id === $actor->id || $to->user_id === $actor->id);

        // Settling across trips decides this for the whole batch.
        if (isset($data['confirmed'])) {
            $confirmed = (bool) $data['confirmed'] && $actor !== null;
        }

        $settlement = $trip->settlements()->create([
            'from_member_id' => $from->id,
            'to_member_id' => $to->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'charge' => round((float) ($data['charge'] ?? 0), 2),
            'gateway' => $data['gateway'] ?? null,
            'gateway_trx_id' => $data['gateway_trx_id'] ?? null,
            'collection_id' => $data['collection_id'] ?? null,
            'batch' => $data['batch'] ?? null,
            'recorded_by' => $actor?->id,
            'settled_on' => $data['settled_on'] ?? now()->toDateString(),
            'confirmed_at' => $confirmed ? now() : null,
            'confirmed_by' => $confirmed ? $actor->id : null,
        ]);

        $amount = Phrase::money($settlement->amount, $trip->currency);
        $method = $settlement->methodEnum()->label();
        $reporter = $actor?->name ?? $from->name;

        $this->logger->log(
            $trip,
            'settlement',
            $confirmed ? Phrase::make(':reporter recorded :from paying :amount to :to by :method', ['reporter' => $reporter, 'from' => $from->name, 'amount' => $amount, 'to' => $to->name, 'method' => $method]) : Phrase::make(':from says they paid :to :amount by :method - waiting for :to to confirm', ['from' => $from->name, 'to' => $to->name, 'amount' => $amount, 'method' => $method, 'to' => $to->name]),
            $actor,
            $from,
            [
                'amount' => (float) $settlement->amount,
                'from' => $from->name,
                'to' => $to->name,
                'method' => $settlement->methodEnum()->value,
                'reference' => $settlement->reference,
                'confirmed' => $confirmed,
            ],
        );

        if ($data['quiet'] ?? false) {
            return $settlement;
        }

        if ($confirmed) {
            $this->logger->notifyMembers(
                $trip,
                Phrase::make('Payment recorded in :trip', ['trip' => $trip->title]),
                Phrase::make(':from paid :amount to :to. Balances updated for everyone.', ['from' => $from->name, 'amount' => $amount, 'to' => $to->name]),
                'success',
                'wallet',
                array_filter([$actor?->id]),
                meta: ['settlement_id' => $settlement->id],
            );
        } elseif ($to->user !== null) {
            // The receiver is the only one who can tell whether the money arrived.
            $this->logger->notifyUser(
                $to->user,
                $trip,
                Phrase::make('Did :from pay you :amount?', ['from' => $from->name, 'amount' => $amount]),
                $settlement->reference
                    ? Phrase::make(':from says they sent :amount by :method (transaction :reference). Confirm it once you see the money.', [
                        'from' => $from->name, 'amount' => $amount, 'method' => $method, 'reference' => $settlement->reference,
                    ])
                    : Phrase::make(':from says they sent :amount by :method. Confirm it once you see the money.', [
                        'from' => $from->name, 'amount' => $amount, 'method' => $method,
                    ]),
                'warning',
                'wallet',
            );
        }

        return $settlement;
    }

    /** The receiver saw the money arrive. */
    public function confirm(Settlement $settlement, User $actor): Settlement
    {
        if ($settlement->confirmed_at !== null) {
            return $settlement;
        }

        if ($settlement->batch !== null) {
            $this->confirmBatch($settlement, $actor);

            return $settlement->refresh();
        }

        $settlement->forceFill(['confirmed_at' => now(), 'confirmed_by' => $actor->id])->save();

        $trip = $settlement->trip;
        $amount = Phrase::money($settlement->amount, $trip->currency);

        $this->logger->log($trip, 'settlement_confirmed', Phrase::make(':actor confirmed receiving :amount from :from', ['actor' => $actor->name, 'amount' => $amount, 'from' => $settlement->from?->name]), $actor, $settlement->from);

        if ($settlement->from?->user !== null && $settlement->from->user_id !== $actor->id) {
            $this->logger->notifyUser($settlement->from->user, $trip, Phrase::make('Payment confirmed'), Phrase::make(':actor confirmed your :amount. You are square for it.', ['actor' => $actor->name, 'amount' => $amount]), 'success', 'check-circle');
        }

        return $settlement;
    }

    /** The receiver says the money never arrived: the report is taken off the ledger. */
    public function reject(Settlement $settlement, User $actor): void
    {
        if ($settlement->batch !== null) {
            $this->rejectBatch($settlement, $actor);

            return;
        }

        $trip = $settlement->trip;
        $amount = Phrase::money($settlement->amount, $trip->currency);
        $from = $settlement->from;

        $settlement->delete();

        $this->logger->log($trip, 'settlement_rejected', Phrase::make(':actor did not receive the :amount :from reported', ['actor' => $actor->name, 'amount' => $amount, 'from' => $from?->name]), $actor, $from);

        if ($from?->user !== null && $from->user_id !== $actor->id) {
            $this->logger->notifyUser($from->user, $trip, Phrase::make('Payment not received'), Phrase::make(':actor has not received your :amount. Check the number and transaction id, then report it again.', ['actor' => $actor->name, 'amount' => $amount]), 'warning', 'alert');
        }
    }

    /** Every payment in a settle-across-trips batch is confirmed together. */
    private function confirmBatch(Settlement $settlement, User $actor): void
    {
        $items = Settlement::query()->where('batch', $settlement->batch)->whereNull('confirmed_at')->with('trip', 'from', 'to')->get();

        foreach ($items as $item) {
            $item->forceFill(['confirmed_at' => now(), 'confirmed_by' => $actor->id])->save();
            $this->logger->log($item->trip, 'settlement_confirmed', Phrase::make(':actor confirmed the settle up across trips', ['actor' => $actor->name]), $actor, $item->from);
        }

        $recorder = $settlement->recorded_by ? User::find($settlement->recorded_by) : null;

        if ($recorder !== null && $recorder->id !== $actor->id) {
            $this->logger->notifyUser($recorder, $settlement->trip, Phrase::make('Settled up with :actor', ['actor' => $actor->name]), Phrase::make(':actor confirmed it. Your balances across :count trips are square.', ['actor' => $actor->name, 'count' => $items->pluck('trip_id')->unique()->count()]), 'success', 'check-circle');
        }
    }

    /** "Not received": the whole batch comes off every trip it touched. */
    private function rejectBatch(Settlement $settlement, User $actor): void
    {
        $items = Settlement::query()->where('batch', $settlement->batch)->with('trip')->get();

        foreach ($items as $item) {
            $item->delete();
            $this->logger->log($item->trip, 'settlement_rejected', Phrase::make(':actor did not accept a settle up across trips', ['actor' => $actor->name]), $actor);
        }

        $recorder = $settlement->recorded_by ? User::find($settlement->recorded_by) : null;

        if ($recorder !== null && $recorder->id !== $actor->id) {
            $this->logger->notifyUser($recorder, $settlement->trip, Phrase::make(':actor did not confirm the settle up', ['actor' => $actor->name]), Phrase::make('They have not received it. Check the number and transaction id, then try again from Balances.'), 'warning', 'alert');
        }
    }
}
