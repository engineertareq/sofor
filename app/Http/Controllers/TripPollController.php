<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Enums\Rsvp;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPoll;
use App\Services\TripActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Deciding together: polls inside a trip, and who is coming.
 *
 * Any traveller on the trip can start a poll and vote. The person who
 * started a poll, or the leader, can close or remove it.
 */
class TripPollController extends Controller
{
    public function __construct(private readonly TripActivityLogger $logger) {}

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $member = $this->traveller($request, $trip);

        if ($trip->polls()->whereNull('closed_at')->count() >= TripPoll::MAX_OPEN) {
            return back()->with('error', __('This trip already has :max open polls. Close one first.', ['max' => TripPoll::MAX_OPEN]));
        }

        $request->merge([
            'options' => collect($request->input('options', []))
                ->map(fn ($option) => trim((string) $option))
                ->filter()
                ->unique(fn (string $option) => mb_strtolower($option))
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:140'],
            'options' => ['required', 'array', 'min:2', 'max:'.TripPoll::MAX_OPTIONS],
            'options.*' => ['string', 'max:80'],
            'multiple' => ['nullable', 'boolean'],
            'closes_at' => ['nullable', 'date', 'after:now'],
        ], [
            'options.min' => __('Give the group at least two different choices.'),
            'options.max' => __('Keep it to :count choices or fewer.', ['count' => TripPoll::MAX_OPTIONS]),
        ]);

        $poll = DB::transaction(function () use ($trip, $request, $data) {
            $poll = $trip->polls()->create([
                'created_by' => $request->user()->id,
                'question' => trim($data['question']),
                'multiple' => (bool) ($data['multiple'] ?? false),
                'closes_at' => $data['closes_at'] ?? null,
            ]);

            foreach ($data['options'] as $position => $label) {
                $poll->options()->create(['label' => $label, 'position' => $position]);
            }

            return $poll;
        });

        $this->logger->log($trip, 'poll_created', Phrase::make(':member asked: :poll', ['member' => $member->name, 'poll' => $poll->question]), $request->user(), $member);
        $this->logger->notifyMembers($trip, Phrase::make(':member asked the group', ['member' => $member->name]), Phrase::make(':poll - vote in :trip.', ['poll' => $poll->question, 'trip' => $trip->title]), 'info', 'chart', [$request->user()->id]);

        return back()->with('status', __('Poll posted. Everyone on the trip has been asked to vote.'));
    }

    /** Tap an option: on a single-choice poll it replaces your vote, on a multiple-choice poll it toggles. */
    public function vote(Request $request, Trip $trip, TripPoll $poll): RedirectResponse
    {
        $member = $this->traveller($request, $trip);
        abort_unless($poll->trip_id === $trip->id, 404);

        if (! $poll->isOpen()) {
            return back()->with('error', __('This poll is closed.'));
        }

        $optionId = (int) $request->validate([
            'option_id' => ['required', 'integer', Rule::exists('trip_poll_options', 'id')->where('poll_id', $poll->id)],
        ])['option_id'];

        DB::transaction(function () use ($poll, $member, $optionId) {
            $existing = $poll->votes()->where('trip_member_id', $member->id)->where('option_id', $optionId)->first();

            if ($existing !== null) {
                $existing->delete();
            } else {
                if (! $poll->multiple) {
                    $poll->votes()->where('trip_member_id', $member->id)->delete();
                }

                $poll->votes()->create(['option_id' => $optionId, 'trip_member_id' => $member->id]);
            }

            // Lets the live view notice the change.
            $poll->touch();
        });

        return back();
    }

    public function close(Request $request, Trip $trip, TripPoll $poll): RedirectResponse
    {
        $this->authorizePoll($request, $trip, $poll);

        if ($poll->closed_at !== null) {
            return back();
        }

        $poll->forceFill(['closed_at' => now()])->save();

        $winner = $poll->options()->withCount('votes')->get()->sortByDesc('votes_count')->first();

        if ($winner !== null && $winner->votes_count > 0) {
            $this->logger->log($trip, 'poll_closed', Phrase::make('Poll closed: :poll - :label won', ['poll' => $poll->question, 'label' => $winner->label]), $request->user());
            $this->logger->notifyMembers($trip, Phrase::make('The group decided: :label', ['label' => $winner->label]), $winner->votes_count === 1
                ? Phrase::make(':question (1 vote)', ['question' => $poll->question])
                : Phrase::make(':question (:count votes)', ['question' => $poll->question, 'count' => $winner->votes_count]), 'success', 'check-circle', [$request->user()->id]);
        }

        return back()->with('status', __('Poll closed.'));
    }

    public function destroy(Request $request, Trip $trip, TripPoll $poll): RedirectResponse
    {
        $this->authorizePoll($request, $trip, $poll);

        $poll->delete();

        return back()->with('status', __('Poll removed.'));
    }

    /**
     * "Are you coming?" Travellers answer for themselves; the leader can
     * answer for someone without an account.
     */
    public function rsvp(Request $request, Trip $trip): RedirectResponse
    {
        $me = $this->traveller($request, $trip);

        $data = $request->validate([
            'rsvp' => ['required', Rule::enum(Rsvp::class)],
            'member_id' => ['nullable', 'integer'],
        ]);

        $member = $me;

        if (filled($data['member_id'] ?? null) && (int) $data['member_id'] !== $me->id) {
            $this->authorize('manageMembers', $trip);
            $member = $trip->members()->whereKey((int) $data['member_id'])->whereNull('user_id')->firstOrFail();
        }

        $answer = Rsvp::from($data['rsvp']);
        $member->forceFill(['rsvp' => $answer, 'rsvp_at' => now()])->save();

        if ($member->is($me)) {
            $this->logger->log($trip, 'rsvp', Phrase::make(':member: :answer', ['member' => $member->name, 'answer' => Phrase::make($answer->key())]), $request->user(), $member);
        }

        return back()->with('status', $member->is($me) ? match ($answer) {
                Rsvp::Going => __('You are in. See you on the trip!'),
                Rsvp::Maybe => __('Marked as maybe. Change it any time.'),
                Rsvp::NotGoing => __('Got it - the group knows you cannot make it.'),
            } : __(':member marked as :label.', ['member' => $member->name, 'label' => mb_strtolower($answer->label())]));
    }

    /** The signed-in traveller's place on this trip, or a 403. */
    private function traveller(Request $request, Trip $trip): TripMember
    {
        $this->authorize('view', $trip);

        $member = $trip->members()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'invited'])
            ->first();

        abort_if($member === null || ! $trip->isActive(), 403);

        return $member;
    }

    private function authorizePoll(Request $request, Trip $trip, TripPoll $poll): void
    {
        abort_unless($poll->trip_id === $trip->id, 404);

        $user = $request->user();

        abort_unless($poll->created_by === $user->id || $user->can('manageMembers', $trip), 403);
    }
}
