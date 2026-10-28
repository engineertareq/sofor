<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\TripTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The cost timeline: every cost of a trip, day by day, with what it was for.
 * Travellers see it at /trips/{trip}/timeline; the leader can open a public
 * link (/timeline/{token}) for anyone, switch it off and on, and replace it.
 */
class TripTimelineController extends Controller
{
    public function __construct(private readonly TripTimeline $timeline) {}

    public function show(Request $request, Trip $trip): View
    {
        $this->authorize('view', $trip);

        return view('trips.timeline', [
            'trip' => $trip,
            'timeline' => $this->timeline->build($trip),
            'canShare' => $request->user()->can('update', $trip),
        ]);
    }

    /** Open or close the public link, and choose whether it says who paid. */
    public function share(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'shared' => ['sometimes', 'boolean'],
            'names' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('names', $data)) {
            $trip->forceFill(['timeline_names' => (bool) $data['names']])->save();

            return back()->with('status', $data['names'] ? __('The public timeline now shows who paid.') : __('Names are hidden on the public timeline.'));
        }

        $shared = (bool) ($data['shared'] ?? true);
        $trip->shareTimeline($shared);

        return back()->with('status', $shared ? __('Your timeline link is on. Anyone with it can see the costs.') : __('Timeline link turned off. Nobody can open it now.'));
    }

    /** A brand new link; the old one stops working. */
    public function rotate(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->rotateTimelineToken();

        return back()->with('status', __('New timeline link made. The old one no longer works.'));
    }

    /** /timeline/{token}: the shared timeline, for anyone with the link while it is on. */
    public function public(string $token): Response
    {
        $trip = Trip::query()->where('timeline_token', $token)->whereNotNull('timeline_shared_at')->firstOrFail();

        return response()
            ->view('timeline.public', [
                'trip' => $trip,
                'timeline' => $this->timeline->build($trip, public: true),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
