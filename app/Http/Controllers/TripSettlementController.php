<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Http\Requests\RecordSettlementRequest;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\ContributionService;
use App\Services\TripActivityLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Contributions are money that actually changed hands inside a trip. Any
 * traveller can pay in, as many times as they like, in any amount - every entry
 * is summed and applied to the balances of the whole trip (and to the dashboard
 * totals of everybody involved).
 */
class TripSettlementController extends Controller
{
    public function __construct(
        private readonly TripActivityLogger $logger,
        private readonly ContributionService $contributions,
    ) {}

    public function store(RecordSettlementRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('recordSettlement', $trip);

        $data = $request->validated();
        $user = $request->user();

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

        $settlement = $this->contributions->record($trip, $from, $to, $user, $data);
        $amount = Money::format($settlement->amount, $trip->currency);

        if ($settlement->isPending()) {
            return back()->with('status', __('Recorded :amount from :from to :to. :to is asked to confirm it arrived.', ['amount' => $amount, 'from' => $from->name, 'to' => $settlement->to->name, 'to' => $settlement->to->name]));
        }

        return back()->with('status', __('Contribution of :amount recorded for :from. Dues across the trip are updated.', ['amount' => $amount, 'from' => $from->name]));
    }

    /** The receiver saw the money arrive. */
    public function confirm(Request $request, Trip $trip, Settlement $settlement): RedirectResponse
    {
        abort_unless($settlement->trip_id === $trip->id, 404);
        $this->authorize('confirmSettlement', [$trip, $settlement]);

        $this->contributions->confirm($settlement, $request->user());

        return back()->with('status', __('Payment confirmed. Balances are up to date.'));
    }

    /** The receiver says the money never arrived. */
    public function reject(Request $request, Trip $trip, Settlement $settlement): RedirectResponse
    {
        abort_unless($settlement->trip_id === $trip->id, 404);
        $this->authorize('confirmSettlement', [$trip, $settlement]);

        $this->contributions->reject($settlement, $request->user());

        return back()->with('status', __('Marked as not received. :from has been told.', ['from' => $settlement->from?->name]));
    }

    public function destroy(Request $request, Trip $trip, Settlement $settlement): RedirectResponse
    {
        abort_unless($settlement->trip_id === $trip->id, 404);

        $this->authorize('deleteSettlement', [$trip, $settlement]);

        $amount = Money::format($settlement->amount, $trip->currency);

        $settlement->delete();

        $this->logger->log(
            $trip,
            'settlement_deleted',
            Phrase::make(':user removed a :amount contribution record', ['user' => $request->user()->name, 'amount' => $amount]),
            $request->user(),
        );

        return back()->with('status', __('Contribution removed. Dues recalculated for the whole trip.'));
    }
}
