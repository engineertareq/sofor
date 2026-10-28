<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cost timeline: every cost, day by day, with what it was for - for the
 * trip's travellers, and for anyone with the link while the leader has it on.
 */
class TripTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $friend;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->friend = User::factory()->create(['name' => 'Asha Rahman']);
        $this->trip = Trip::factory()->create([
            'user_id' => $this->leader->id, 'title' => 'Sajek Valley weekend', 'destination' => 'Sajek Valley',
            'starts_on' => '2026-10-18', 'ends_on' => '2026-10-20',
        ]);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->friend->name, 'email' => $this->friend->email]);

        $this->cost('Bus tickets booked online', 400, 'tickets', '2026-10-12', 'A week ahead, so we sat together.');
        $this->cost('Megh Bilash cottage', 6000, 'accommodation', '2026-10-19', 'Two nights over the clouds.');
        $this->cost('Bamboo chicken', 1450, 'food', '2026-10-19');
        $this->cost('Jeep back down', 3200, 'transport', '2026-10-20');
    }

    private function cost(string $title, float $amount, string $category, string $on, ?string $notes = null): void
    {
        $this->actingAs($this->leader)->post(route('trips.expenses.store', $this->trip), [
            'title' => $title, 'amount' => $amount, 'category' => $category, 'spent_on' => $on, 'notes' => $notes,
            'trip_member_id' => $this->trip->members()->where('user_id', $this->leader->id)->value('id'),
            'split_type' => 'equal', 'participant_ids' => $this->trip->members()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();
    }

    public function test_travellers_see_every_cost_day_by_day_with_its_reason(): void
    {
        $page = $this->actingAs($this->friend)->get(route('trips.timeline', $this->trip))->assertOk();

        $page->assertSeeInOrder(['Before the trip', 'Bus tickets booked online', 'Day 2', 'Megh Bilash cottage', 'Bamboo chicken', 'Day 3', 'Jeep back down'])
            ->assertSee('A week ahead, so we sat together.')
            ->assertSee('৳11,050')                       // the whole trip
            ->assertSee('৳7,850 so far', false)           // the running total by the end of day 2: 400 + 6,000 + 1,450
            ->assertSee('Rifat paid')
            ->assertSee('Split 2 ways', false)
            ->assertSee('The trip leader can turn on a link to share it with anyone.')
            ->assertDontSee('role="switch"', false);       // only the leader switches it

        $this->actingAs(User::factory()->create())->get(route('trips.timeline', $this->trip))->assertForbidden();
    }

    public function test_the_map_follows_the_trip_day_by_day(): void
    {
        $this->cost('Chander gari, Khagrachari to Sajek', 3600, 'transport', '2026-10-18', 'Shared jeep up the hills.');

        $map = app(\App\Services\TripTimeline::class)->build($this->trip->refresh())['map'];
        $stops = array_column($map['stops'], 'name', 'id');

        // Day 1 names Khagrachari and Sajek (Rangamati), in that order, so the route runs that way.
        $this->assertSame(['BD-29', 'BD-56'], array_slice(array_keys($stops), 0, 2));
        $this->assertSame(1, $map['stops'][0]['number']);
        $this->assertSame(1.0, $map['marks']['2026-10-18']);   // all of the route travelled by the end of day 1
        $this->assertSame(0.0, $map['marks']['2026-10-12']);   // nothing yet before the trip

        $this->actingAs($this->friend)->get(route('trips.timeline', $this->trip))
            ->assertSee('THE ROUTE')
            ->assertSee('tl-map-stop', false)
            ->assertSee('Rangamati');
    }

    public function test_the_leader_shares_it_hides_names_switches_it_off_and_on_and_replaces_it(): void
    {
        $this->actingAs($this->leader)->get(route('trips.timeline', $this->trip))->assertSee('role="switch"', false);

        // On: anyone with the link sees the costs, never indexed, no receipts.
        $this->actingAs($this->leader)->put(route('trips.timeline.share', $this->trip), ['shared' => 1])->assertRedirect();
        $link = $this->trip->refresh()->timelineUrl();
        $this->assertNotNull($link);

        auth()->logout();
        $this->get($link)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Megh Bilash cottage')
            ->assertSee('Two nights over the clouds.')
            ->assertSee('Rifat paid')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('/receipt', false)
            ->assertDontSee($this->friend->email);

        // Names off: it no longer says who paid.
        $this->actingAs($this->leader)->put(route('trips.timeline.share', $this->trip), ['names' => 0]);
        auth()->logout();
        $this->get($link)->assertOk()->assertDontSee('Rifat paid')->assertDontSee('Rifat');

        // Off: the link stops working. On again: the same link is back.
        $this->actingAs($this->leader)->put(route('trips.timeline.share', $this->trip), ['shared' => 0]);
        auth()->logout();
        $this->get($link)->assertNotFound();

        $this->actingAs($this->leader)->put(route('trips.timeline.share', $this->trip), ['shared' => 1]);
        $this->assertSame($link, $this->trip->refresh()->timelineUrl());
        auth()->logout();
        $this->get($link)->assertOk();

        // A new link: the old one is dead, the new one works.
        $this->actingAs($this->leader)->post(route('trips.timeline.rotate', $this->trip))->assertRedirect();
        $fresh = $this->trip->refresh()->timelineUrl();
        $this->assertNotSame($link, $fresh);
        auth()->logout();
        $this->get($link)->assertNotFound();
        $this->get($fresh)->assertOk();
    }

    public function test_only_the_leader_controls_the_link_but_travellers_can_copy_it(): void
    {
        $this->actingAs($this->friend)->put(route('trips.timeline.share', $this->trip), ['shared' => 1])->assertForbidden();
        $this->actingAs($this->friend)->post(route('trips.timeline.rotate', $this->trip))->assertForbidden();
        $this->assertFalse($this->trip->refresh()->timelineIsPublic());

        $this->trip->shareTimeline(true);
        $this->actingAs($this->friend)->get(route('trips.timeline', $this->trip))
            ->assertSee($this->trip->timelineUrl(), false)
            ->assertDontSee('role="switch"', false);
    }

    public function test_a_link_that_was_never_shared_or_made_up_is_not_found_and_search_engines_stay_out(): void
    {
        $this->get('/timeline/'.str_repeat('a', 32))->assertNotFound();

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertSee("Disallow: /timeline/\n", false);
    }
}
