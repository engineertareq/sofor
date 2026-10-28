<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Enums\MemberStatus;
use App\Http\Requests\InviteMemberRequest;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\TripActivityLogger;
use App\Services\TripInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripMemberController extends Controller
{
    public function __construct(
        private readonly TripInvitationService $invitations,
        private readonly TripActivityLogger $logger,
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

        $member->update([
            'name' => $data['name'] ?? $member->name,
            'share_units' => $data['share_units'] ?? $member->share_units,
            'include_in_split' => $request->has('include_in_split')
                ? $request->boolean('include_in_split')
                : $member->include_in_split,
            // The leader's own row always stays in the trip.
            'status' => $member->isOwner() ? $member->status->value : ($data['status'] ?? $member->status->value),
        ]);

        $this->logger->log(
            $trip,
            'member_updated',
            Phrase::make(':user updated :member\'s split settings', ['user' => $request->user()->name, 'member' => $member->name]),
            $request->user(),
            $member,
            [
                'share_units' => $member->share_units,
                'include_in_split' => $member->include_in_split,
            ],
        );

        return back()->with('status', __(':member\'s split settings saved.', ['member' => $member->name]));
    }

    public function destroy(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->ensureBelongsToTrip($trip, $member);

        abort_if($member->isOwner(), 403, __('The trip leader cannot be removed.'));
        abort_if($trip->isEnded(), 403, __('This trip has ended.'));

        $name = $member->name;
        $hasHistory = $member->shares()->exists() || $trip->expenses()->where('trip_member_id', $member->id)->exists();

        if ($hasHistory) {
            $member->update([
                'status' => MemberStatus::Removed->value,
                'include_in_split' => false,
            ]);
            $message = __(':name was removed from future splits. Their past costs stay in the history.', ['name' => $name]);
        } else {
            $member->delete();
            $message = __(':name was removed from the trip.', ['name' => $name]);
        }

        $this->logger->log($trip, 'member_removed', Phrase::make(':user removed :name', ['user' => $request->user()->name, 'name' => $name]), $request->user(), null);
        $this->logger->notifyMembers($trip, Phrase::make(':name left :trip', ['name' => $name, 'trip' => $trip->title]), Phrase::make('Balances were recalculated.'), 'warning', 'users', [$request->user()->id]);

        return back()->with('status', $message);
    }

    public function reinvite(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        $this->ensureBelongsToTrip($trip, $member);

        $member->update([
            'status' => $member->isRegistered() ? MemberStatus::Active->value : MemberStatus::Invited->value,
            'invited_at' => now(),
        ]);

        if ($member->user !== null) {
            $this->logger->notifyUser(
                $member->user,
                $trip,
                Phrase::make('Reminder: :trip', ['trip' => $trip->title]),
                Phrase::make('You still have a pending split in this trip. Open it to see your dues.'),
                'info',
                'bell',
            );
        }

        return back()->with('status', __('Reminder sent to :member.', ['member' => $member->name]));
    }

    private function ensureBelongsToTrip(Trip $trip, TripMember $member): void
    {
        abort_unless($member->trip_id === $trip->id, 404);
    }
}
