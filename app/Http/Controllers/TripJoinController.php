<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\TripEstimator;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripJoinController extends Controller
{
    public const SESSION_KEY = 'invite.code';

    public function __construct(private readonly TripInvitationService $invitations) {}

    public function show(Request $request, string $code, TripLedger $ledger, TripEstimator $estimator): View|RedirectResponse
    {
        $trip = Trip::where('invite_code', $code)->firstOrFail();

        // Admin accounts never join trips; show them the console's copy instead.
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.trips.show', $trip);
        }

        // Already on the trip? Straight to the dashboard for it.
        if ($request->user() !== null && $request->user()->can('view', $trip)) {
            return redirect()->route('trips.show', $trip)->with('status', __('You are already part of :trip.', ['trip' => $trip->title]));
        }

        // Remember the invite so signing in or registering from here joins the
        // trip instantly instead of dropping the traveller on the dashboard.
        if ($request->user() === null) {
            $request->session()->put(self::SESSION_KEY, $trip->invite_code);
        }

        $ledgerData = $ledger->summarize($trip, $request->user());

        return view('trips.join', [
            'trip' => $trip,
            'ledger' => $ledgerData,
            // What the trip is likely to cost before saying yes.
            'estimate' => $trip->hasBudget() || $trip->isEnded() ? null : $estimator->estimate(
                $trip->destination,
                $trip->currency,
                $trip->starts_on && $trip->ends_on ? (int) $trip->starts_on->diffInDays($trip->ends_on) + 1 : null,
                $trip->id,
            ),
            'memberNames' => $trip->members()->where('status', '!=', 'removed')->pluck('name'),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $trip = Trip::where('invite_code', $code)->firstOrFail();

        $request->session()->forget(self::SESSION_KEY);

        if ($request->user()->can('view', $trip)) {
            return redirect()->route('trips.show', $trip)->with('status', __('You are already part of :trip.', ['trip' => $trip->title]));
        }

        // Back to the preview: the trip page itself is for its travellers only.
        if ($trip->isEnded()) {
            return redirect()->route('trips.join.show', $trip->invite_code)->with('error', __('This trip has ended, so new travellers cannot join.'));
        }

        $this->invitations->joinViaCode($trip, $request->user());

        return redirect()->route('trips.show', $trip)->with('status', __('You joined :trip. Your share of every cost is now visible.', ['trip' => $trip->title]));
    }
}
