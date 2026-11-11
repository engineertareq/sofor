<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\TripInvitationService;
use App\Services\TripManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripMemberController extends Controller
{
    public function __construct(
        private readonly TripInvitationService $invitations,
        private readonly TripManager $trips,
    ) {}

    public function store(InviteMemberRequest $request, Trip $trip): RedirectResponse
    {
        // The request already refused anything that would duplicate a traveller,
        // this is the last line of defence against the unique trip/member key.
        abort_if($trip->isEnded(), 403, __('This trip has ended, so travellers can no longer be added.'));

        $result = $this->invitations->invite($trip, $request->user(), $request->validated());

        return back()->with($result['self'] ? 'warning' : 'status', $result['message']);
    }

    public function update(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->ensureBelongsToTrip($trip, $member);

        abort_if($trip->isEnded(), 403, __('This trip has ended.'));

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'share_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'include_in_split' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,invited,declined,removed'],
        ]);

        // A checkbox that was not on the form leaves the setting as it is.
        unset($data['include_in_split']);

        if ($request->has('include_in_split')) {
            $data['include_in_split'] = $request->boolean('include_in_split');
        }

        $this->trips->updateMember($trip, $member, $request->user(), $data);

        return back()->with('status', __(':member\'s split settings saved.', ['member' => $member->name]));
    }

    public function destroy(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->ensureBelongsToTrip($trip, $member);

        abort_if($member->isOwner(), 403, __('The trip leader cannot be removed.'));
        abort_if($trip->isEnded(), 403, __('This trip has ended.'));

        $name = $member->name;

        return back()->with('status', $this->trips->removeMember($trip, $member, $request->user())
            ? __(':name was removed from future splits. Their past costs stay in the history.', ['name' => $name])
            : __(':name was removed from the trip.', ['name' => $name]));
    }

    public function reinvite(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->ensureBelongsToTrip($trip, $member);

        $this->trips->remindMember($trip, $member);

        return back()->with('status', __('Reminder sent to :member.', ['member' => $member->name]));
    }

    private function ensureBelongsToTrip(Trip $trip, TripMember $member): void
    {
        abort_unless($member->trip_id === $trip->id, 404);
    }
}
