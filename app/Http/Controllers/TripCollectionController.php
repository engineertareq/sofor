<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Models\Trip;
use App\Models\TripCollection;
use App\Models\TripMember;
use App\Services\TripActivityLogger;
use App\Services\TripPot;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * The leader collects an advance: so much per traveller, paid to one person
 * who then pays shared costs from it.
 */
class TripCollectionController extends Controller
{
    public const MAX_OPEN = 10;

    public function __construct(private readonly TripActivityLogger $logger) {}

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        if (! $trip->isActive()) {
            return back()->with('error', __('This trip has ended, so it cannot collect advances any more.'));
        }

        $travellerIds = $trip->members()->whereIn('status', ['active', 'invited'])->pluck('id')->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:80'],
            'amount_per_person' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'collector_member_id' => ['required', 'integer', Rule::in($travellerIds)],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:240'],
        ], [
            'collector_member_id.in' => __('Pick a traveller on this trip to hold the money.'),
            'due_on.after_or_equal' => __('The due date cannot be in the past.'),
        ]);

        if ($trip->collections()->whereNull('closed_at')->count() >= self::MAX_OPEN) {
            return back()->with('error', __('This trip already has :max open collections. Close one first.', ['max' => self::MAX_OPEN]));
        }

        $collection = $trip->collections()->create($data + ['created_by' => $request->user()->id]);
        $collector = $collection->collector;
        $amount = Phrase::money($collection->amount_per_person, $trip->currency);

        $this->logger->log($trip, 'collection_started', Phrase::make(':user started collecting :amount a person for :collection', ['user' => $request->user()->name, 'amount' => $amount, 'collection' => $collection->title]), $request->user(), $collector);

        $this->logger->notifyMembers(
            $trip,
            Phrase::make(':collection: :amount a person', ['collection' => $collection->title, 'amount' => $amount]),
            $collection->due_on
                ? Phrase::make('Send it to :collector by :date. :collector pays shared costs from the pot.', ['collector' => $collector->name, 'date' => Phrase::date($collection->due_on)])
                : Phrase::make('Send it to :collector. :collector pays shared costs from the pot.', ['collector' => $collector->name]),
            'info',
            'wallet',
            array_filter([$request->user()->id, $collector->user_id]),
        );

        return back()->with('status', __('Collection started. Everyone has been told to send :amount to :collector.', ['amount' => $amount, 'collector' => $collector->name]));
    }

    /** Close a collection (no more advances expected), or open it again. */
    public function toggle(Request $request, Trip $trip, TripCollection $collection): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_unless($collection->trip_id === $trip->id, 404);

        $collection->forceFill(['closed_at' => $collection->isOpen() ? now() : null])->save();

        return back()->with('status', $collection->isOpen() ? __(':collection is open again.', ['collection' => $collection->title]) : __(':collection is closed.', ['collection' => $collection->title]));
    }

    /** Payments already made stay in the ledger as ordinary payments. */
    public function destroy(Request $request, Trip $trip, TripCollection $collection): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_unless($collection->trip_id === $trip->id, 404);

        $title = $collection->title;
        $collection->delete();

        $this->logger->log($trip, 'collection_removed', Phrase::make(':user removed the collection :title', ['user' => $request->user()->name, 'title' => $title]), $request->user());

        return back()->with('status', __(':title removed. Advances already paid stay in the ledger.', ['title' => $title]));
    }

    /** Tell everybody who has not paid in yet. Once every 12 hours per collection. */
    public function nudge(Request $request, Trip $trip, TripCollection $collection, TripPot $pot): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_unless($collection->trip_id === $trip->id && $collection->isOpen(), 404);

        $key = 'collection-nudge:'.$collection->id;

        if (Cache::has($key)) {
            return back()->with('error', __('Everyone was reminded recently. Try again later.'));
        }

        $summary = $pot->summaries($trip)->firstWhere('collection.id', $collection->id);
        $sent = 0;

        foreach ($summary['rows']->filter(fn (array $row) => $row['left'] > 0.009) as $row) {
            /** @var TripMember $member */
            $member = $row['member'];

            if ($member->user === null) {
                continue;
            }

            $this->logger->notifyUser(
                $member->user,
                $trip,
                Phrase::make(':collection: :amount to :name', ['collection' => $collection->title, 'amount' => Phrase::money($row['left'], $trip->currency), 'name' => $summary['collector']->name]),
                $collection->due_on
                    ? Phrase::make('Your advance for :trip is still open - due :date. Pay :collector directly, then tap "I paid" on the trip.', ['trip' => $trip->title, 'date' => Phrase::date($collection->due_on), 'collector' => $summary['collector']->name])
                    : Phrase::make('Your advance for :trip is still open. Pay :collector directly, then tap "I paid" on the trip.', ['trip' => $trip->title, 'collector' => $summary['collector']->name]),
                'warning',
                'wallet',
            );
            $sent++;
        }

        Cache::put($key, true, now()->addHours(12));

        return back()->with('status', $sent === 0 ? __('Nobody with an account still owes this advance. Share the trip link with the others.') : ($sent === 1 ? __('Reminded 1 traveller.') : __('Reminded :count travellers.', ['count' => $sent])));
    }
}
