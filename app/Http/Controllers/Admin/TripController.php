<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TripActivityLogger;
use App\Services\TripLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripController extends Controller
{
    public function __construct(
        private readonly TripActivityLogger $logger,
        private readonly TripLedger $ledger,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString() ?: 'all';

        $trips = Trip::query()
            ->with('owner')
            ->withCount(['expenses', 'members'])
            ->withSum('expenses', 'amount')
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'ended', fn ($query) => $query->ended())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.trips.index', [
            'trips' => $trips,
            'filters' => ['q' => $search, 'status' => $status],
            'counts' => [
                'all' => Trip::count(),
                'active' => Trip::active()->count(),
                'ended' => Trip::ended()->count(),
            ],
        ]);
    }

    /**
     * A read only copy of the trip ledger for support. It reuses the traveller
     * partials with every action switched off, and no viewer, because an
     * administrator is never one of the travellers.
     */
    public function show(Trip $trip): View
    {
        $trip->load('owner');

        return view('admin.trips.show', [
            'trip' => $trip,
            'ledger' => $this->ledger->summarize($trip),
            'expenses' => $trip->expenses()->with(['payer', 'shares.member', 'author'])->get(),
            'settlements' => $trip->settlements()->with(['from', 'to'])->take(30)->get(),
            'activities' => $trip->activities()->with(['user', 'member'])->take(40)->get(),
        ]);
    }

    public function end(Request $request, Trip $trip): RedirectResponse
    {
        if ($trip->isEnded()) {
            return back()->with('error', $trip->title.' has already ended.');
        }

        $trip->forceFill(['status' => 'ended', 'ended_at' => now()])->save();

        $this->logger->log(
            $trip,
            'trip_ended',
            'A Sofor administrator ended this trip',
            $request->user(),
        );

        $this->logger->notifyMembers(
            $trip,
            $trip->title.' was closed by Sofor',
            'An administrator ended this trip, so costs are now locked.',
            'warning',
            'lock',
            [$request->user()->id],
        );

        return back()->with('status', $trip->title.' ended by admin.');
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $title = $trip->title;
        // Deleted from its own page, there is nothing left to go back to.
        $fromTripPage = url()->previous() === route('admin.trips.show', $trip);
        $trip->delete();

        return ($fromTripPage ? redirect()->route('admin.trips.index') : back())
            ->with('status', $title.' was deleted with all its costs.');
    }
}
