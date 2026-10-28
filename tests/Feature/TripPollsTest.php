<?php

namespace Tests\Feature;

use App\Enums\Rsvp;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\TripPoll;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Polls inside a trip, and "are you coming?". */
class TripPollsTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $leader;

    private User $asha;

    private User $bilal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);
        $this->bilal = User::factory()->create(['name' => 'Bilal Khan']);
        $this->trip = Trip::factory()->create(['user_id' => $this->leader->id]);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->bilal->name, 'email' => $this->bilal->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => 'Kamal (no phone)']);
    }

    private function member(User $user): TripMember
    {
        return $this->trip->members()->where('user_id', $user->id)->firstOrFail();
    }

    private function poll(User $by, array $data = []): TripPoll
    {
        $this->actingAs($by)->post(route('trips.polls.store', $this->trip), $data + [
            'question' => 'Which bus?', 'options' => ['Hanif', 'Shyamoli', '  ', 'hanif'],
        ])->assertSessionHasNoErrors();

        return TripPoll::latest('id')->firstOrFail();
    }

    private function vote(User $user, TripPoll $poll, string $label): void
    {
        $this->actingAs($user)->post(route('trips.polls.vote', [$this->trip, $poll]), [
            'option_id' => $poll->options()->where('label', $label)->value('id'),
        ])->assertSessionHasNoErrors();
    }

    /** @return array<string, int> */
    private function tally(TripPoll $poll): array
    {
        return $poll->options()->withCount('votes')->get()->pluck('votes_count', 'label')->all();
    }

    public function test_any_traveller_asks_the_group_and_everyone_is_told(): void
    {
        Notification::fake();

        $poll = $this->poll($this->asha);

        // Blank and repeated choices are dropped.
        $this->assertSame(['Hanif', 'Shyamoli'], $poll->options()->pluck('label')->all());
        Notification::assertSentTo($this->leader, TripNotification::class, fn ($n) => str_contains($n->title, 'Asha Rahman asked the group'));
        Notification::assertNotSentTo($this->asha, TripNotification::class);

        $this->actingAs($this->bilal)->post(route('trips.polls.store', $this->trip), ['question' => 'Only one?', 'options' => ['Yes']])
            ->assertSessionHasErrors('options');

        $this->actingAs(User::factory()->create())->post(route('trips.polls.store', $this->trip), ['question' => 'Hi', 'options' => ['a', 'b']])
            ->assertForbidden();
    }

    public function test_a_single_choice_vote_moves_and_a_second_tap_takes_it_back(): void
    {
        $poll = $this->poll($this->leader);

        $this->vote($this->asha, $poll, 'Hanif');
        $this->vote($this->bilal, $poll, 'Hanif');
        $this->assertSame(['Hanif' => 2, 'Shyamoli' => 0], $this->tally($poll));

        $this->vote($this->asha, $poll, 'Shyamoli');
        $this->assertSame(['Hanif' => 1, 'Shyamoli' => 1], $this->tally($poll));

        $this->vote($this->asha, $poll, 'Shyamoli');
        $this->assertSame(['Hanif' => 1, 'Shyamoli' => 0], $this->tally($poll));
    }

    public function test_a_multiple_choice_poll_keeps_every_pick(): void
    {
        $poll = $this->poll($this->leader, ['question' => 'What should we do?', 'options' => ['Hike', 'Boat', 'Market'], 'multiple' => '1']);

        $this->vote($this->asha, $poll, 'Hike');
        $this->vote($this->asha, $poll, 'Boat');

        $this->assertSame(['Hike' => 1, 'Boat' => 1, 'Market' => 0], $this->tally($poll));
    }

    public function test_closing_announces_the_winner_and_stops_voting(): void
    {
        Notification::fake();
        $poll = $this->poll($this->asha);
        $this->vote($this->leader, $poll, 'Shyamoli');
        $this->vote($this->bilal, $poll, 'Shyamoli');

        // Only the person who asked, or the leader.
        $this->actingAs($this->bilal)->post(route('trips.polls.close', [$this->trip, $poll]))->assertForbidden();
        $this->actingAs($this->asha)->post(route('trips.polls.close', [$this->trip, $poll]))->assertRedirect();

        Notification::assertSentTo($this->bilal, TripNotification::class, fn ($n) => $n->title === 'The group decided: Shyamoli');

        $this->vote($this->asha, $poll, 'Hanif');
        $this->assertSame(['Hanif' => 0, 'Shyamoli' => 2], $this->tally($poll));

        $this->actingAs($this->leader)->get(route('trips.show', $this->trip))->assertSee('Which bus?')->assertSee('Picked');
    }

    public function test_a_poll_past_its_closing_time_is_closed(): void
    {
        $poll = $this->poll($this->leader, ['closes_at' => now()->addHour()->format('Y-m-d H:i:s')]);
        $this->assertTrue($poll->isOpen());

        $this->travel(2)->hours();
        $this->assertFalse($poll->refresh()->isOpen());

        $this->actingAs($this->asha)->post(route('trips.polls.vote', [$this->trip, $poll]), [
            'option_id' => $poll->options()->value('id'),
        ])->assertSessionHas('error');
    }

    public function test_travellers_say_whether_they_are_coming(): void
    {
        $this->actingAs($this->asha)->get(route('trips.show', $this->trip))->assertSee('Are you coming to');

        $this->put(route('trips.rsvp', $this->trip), ['rsvp' => 'going'])->assertSessionHas('status');
        $this->assertSame(Rsvp::Going, $this->member($this->asha)->rsvp);
        $this->get(route('trips.show', $this->trip))->assertDontSee('Are you coming to');

        // Only the leader answers for someone else, and only for someone without an account.
        $kamal = $this->trip->members()->where('name', 'Kamal (no phone)')->firstOrFail();
        $this->put(route('trips.rsvp', $this->trip), ['rsvp' => 'maybe', 'member_id' => $kamal->id])->assertForbidden();

        $this->actingAs($this->leader)->put(route('trips.rsvp', $this->trip), ['rsvp' => 'maybe', 'member_id' => $kamal->id]);
        $this->assertSame(Rsvp::Maybe, $kamal->refresh()->rsvp);

        $this->put(route('trips.rsvp', $this->trip), ['rsvp' => 'going', 'member_id' => $this->member($this->bilal)->id])->assertNotFound();
        $this->assertNull($this->member($this->bilal)->rsvp);

        $this->get(route('trips.show', $this->trip))->assertSee('2 going')->assertSee('1 maybe');
    }
}
