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

        $settlement = $this->contributions->recordAs($trip, $user, $data);
        $from = $settlement->from;
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
