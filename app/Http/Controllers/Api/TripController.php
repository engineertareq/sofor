<?php

namespace App\Http\Controllers\Api;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTripRequest;
use App\Http\Requests\Api\UpdateTripRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\PaymentReminders;
use App\Services\PortfolioService;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use App\Services\TripManager;
use App\Services\TripSnapshot;
use App\Support\Api\Present;
use App\Support\Poisha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Trips and the travellers on them. Anything that changes a trip answers with
 * the trip's whole snapshot (TripSnapshot), so the app replaces its copy in
 * one step and never shows half of a change.
 */
class TripController extends Controller
{
    public function __construct(
        private readonly TripManager $trips,
        private readonly TripSnapshot $snapshots,
        private readonly TripInvitationService $invitations,
    ) {}

    /** The home screen and the trip list: every trip, with what this traveller owes or is owed overall. */
    public function index(Request $request, PortfolioService $portfolio): JsonResponse
    {
        $user = $request->user();

        $trips = Trip::query()
            ->visibleTo($user)
            ->with(['owner', 'members'])
            ->withCount('expenses')
            ->withSum('expenses', 'amount')
            ->latest('last_activity_at')
            ->latest('id')
            ->get();

        $mine = $portfolio->forUser($user);

        return response()->json([
            'trips' => $trips->map(fn (Trip $trip) => Present::trip($trip, $user, $mine['trips'][$trip->id] ?? null))->all(),
            'totals' => [
                'due' => Poisha::of($mine['totals']['due']),
                'credit' => Poisha::of($mine['totals']['credit']),
            ],
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->trips->create($request->user(), $request->validated());

        return $this->snapshot($request, $trip, __('Trip created. Add the first cost to see the split.'), 201);
    }

    /** `?stamp=` from the last snapshot: unchanged trips answer 304 with nothing to download. */
    public function show(Request $request, Trip $trip): JsonResponse|Response
    {
        $this->authorize('view', $trip);

        if ($request->filled('stamp') && hash_equals($trip->activityStamp(), (string) $request->query('stamp'))) {
            return response()->noContent(304);
        }

        TripMember::where('trip_id', $trip->id)->where('user_id', $request->user()->id)->update(['last_seen_at' => now()]);

        return $this->snapshot($request, $trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->trips->update($trip, $request->user(), $request->validated());

        return $this->snapshot($request, $trip, __('Trip details updated.'));
    }

    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $title = $trip->title;
        $trip->delete();

        return response()->json(['message' => __(':title was deleted.', ['title' => $title])]);
    }

    public function end(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('end', $trip);

        $this->trips->end($trip, $request->user());

        return $this->snapshot($request, $trip, __('Trip ended. Costs are locked and the final settlement has been calculated.'));
    }

    public function leave(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();
        $member = $trip->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            throw ValidationException::withMessages(['trip' => __('You are not part of this trip.')]);
        }

        if ($member->isOwner()) {
            throw ValidationException::withMessages(['trip' => __('The trip leader cannot leave. End or delete the trip instead.')]);
        }

        $this->trips->leave($trip, $member, $user);

        return response()->json(['message' => __('You left :trip.', ['trip' => $trip->title])]);
    }

    public function rotateInvite(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageMembers', $trip);

        $this->trips->rotateInvite($trip, $request->user());

        return $this->snapshot($request, $trip, __('New invite link generated. The old link no longer works.'));
    }

    /* ------------------------------------------------------------------
       Travellers
    ------------------------------------------------------------------ */

    public function addMember(InviteMemberRequest $request, Trip $trip): JsonResponse
    {
        abort_if($trip->isEnded(), 403, __('This trip has ended, so travellers can no longer be added.'));

        $result = $this->invitations->invite($trip, $request->user(), $request->validated());

        return $this->snapshot($request, $trip, $result['message'], 201);
    }

    public function updateMember(Request $request, Trip $trip, TripMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_if($trip->isEnded(), 403, __('This trip has ended.'));

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'share_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'include_in_split' => ['sometimes', 'boolean'],
        ]);

        $this->trips->updateMember($trip, $member, $request->user(), $data);

        return $this->snapshot($request, $trip, __(':member\'s split settings saved.', ['member' => $member->name]));
    }

    public function removeMember(Request $request, Trip $trip, TripMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_if($member->isOwner(), 403, __('The trip leader cannot be removed.'));
        abort_if($trip->isEnded(), 403, __('This trip has ended.'));

        $name = $member->name;

        return $this->snapshot($request, $trip, $this->trips->removeMember($trip, $member, $request->user())
            ? __(':name was removed from future splits. Their past costs stay in the history.', ['name' => $name])
            : __(':name was removed from the trip.', ['name' => $name]));
    }

    /** "Remind" on a traveller who owes: in the app and by email, free. */
    public function remind(Request $request, Trip $trip, TripMember $member, PaymentReminders $reminders): JsonResponse
    {
        $this->authorize('remind', [$trip, $member]);

        $result = $reminders->remind($trip, $member, $request->user());

        if (! $result['ok']) {
            throw ValidationException::withMessages(['member' => $result['message']]);
        }

        return response()->json(['message' => $result['message']]);
    }

    /* ------------------------------------------------------------------
       Invite links
    ------------------------------------------------------------------ */

    /** What an invite link leads to, before joining. */
    public function invite(Request $request, string $code, TripLedger $ledger): JsonResponse
    {
        $trip = Trip::where('invite_code', $code)->with('owner')->firstOrFail();
        $user = $request->user();

        return response()->json([
            'trip' => [
                'id' => $trip->id,
                'title' => $trip->title,
                'destination' => $trip->destination,
                'cover' => array_key_exists((string) $trip->cover, Trip::COVERS) ? $trip->cover : 'aurora',
                'icon' => $trip->icon,
                'currency' => $trip->currency,
                'starts_on' => Present::day($trip->starts_on),
                'ends_on' => Present::day($trip->ends_on),
                'ended' => $trip->isEnded(),
                'owner_name' => $trip->owner?->name,
                'spent' => Poisha::of($trip->expenses()->sum('amount')),
            ],
            'travellers' => $trip->members()->where('status', '!=', MemberStatus::Removed->value)->pluck('name')->all(),
            'already_member' => $user->can('view', $trip),
        ]);
    }

    public function join(Request $request, string $code): JsonResponse
    {
        $trip = Trip::where('invite_code', $code)->firstOrFail();
        $user = $request->user();

        if ($user->can('view', $trip)) {
            return $this->snapshot($request, $trip, __('You are already part of :trip.', ['trip' => $trip->title]));
        }

        if ($trip->isEnded()) {
            throw ValidationException::withMessages(['trip' => __('This trip has ended, so new travellers cannot join.')]);
        }

        $this->invitations->joinViaCode($trip, $user);

        return $this->snapshot($request, $trip, __('You joined :trip. Your share of every cost is now visible.', ['trip' => $trip->title]), 201);
    }

    private function snapshot(Request $request, Trip $trip, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(
            $this->snapshots->for($trip->refresh(), $request->user()) + ($message !== null ? ['message' => $message] : []),
            $status,
        );
    }
}
