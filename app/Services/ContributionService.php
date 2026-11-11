<?php

namespace App\Services;

use App\Support\Phrase;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

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
     * A signed in traveller writes a payment down, from the website or the app.
     * Works out who paid and who received it when either was left out, and
     * refuses a payment between two other people unless the leader records it.
     *
     * @param  array<string, mixed>  $data  validated by RecordSettlementRequest
     */
    public function recordAs(Trip $trip, User $user, array $data): Settlement
    {
        $members = $trip->members()->get();
        $current = $members->firstWhere('user_id', $user->id);

        $from = $members->firstWhere('id', (int) ($data['from_member_id'] ?? 0))
            ?? $current
            ?? $members->firstWhere('role', 'owner')
            ?? $members->first();

        if (! $from instanceof TripMember) {
            throw ValidationException::withMessages([
                'amount' => __('This trip has no travellers to record a contribution for yet.'),
            ]);
        }

        $to = $members->firstWhere('id', (int) ($data['to_member_id'] ?? 0));

        // An advance always goes to whoever holds the pot.
        $collection = filled($data['collection_id'] ?? null) ? $trip->collections()->find((int) $data['collection_id']) : null;

        if ($collection !== null) {
            $to = $members->firstWhere('id', $collection->collector_member_id);

            if ($to === null || $to->is($from)) {
                throw ValidationException::withMessages([
                    'collection_id' => __('The person holding the pot does not pay into it.'),
                ]);
            }
        }

        // The leader may record any payment. Everybody else records payments
        // they took part in - money they paid, or money they received - not a
        // transfer between two other people.
        $isLeader = $user->isAdmin() || $trip->user_id === $user->id;
        $involved = $current !== null && ($from->is($current) || $to?->is($current));

        if (! $isLeader && ! $involved) {
            throw ValidationException::withMessages([
                'from_member_id' => __('You can record payments you made or received. Ask the trip leader to record this one.'),
            ]);
        }

        return $this->record($trip, $from, $to, $user, $data);
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
            // Only the app sends one; leaving it out otherwise keeps the website's
            // payments working on a database that has not been migrated yet.
            ...(filled($data['client_uuid'] ?? null) ? ['client_uuid' => $data['client_uuid']] : []),
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
