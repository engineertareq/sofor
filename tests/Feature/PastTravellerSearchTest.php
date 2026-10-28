<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Starting a new trip with people from earlier trips: the search only ever
 * reaches trips the traveller can already open, and each person shows once.
 */
class PastTravellerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function tripFor(User $leader, string $title = 'Sajek cloud trip'): Trip
    {
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => $title]);

        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        return $trip;
    }

    private function invite(Trip $trip, User $inviter, array $row): void
    {
        app(TripInvitationService::class)->invite($trip, $inviter, $row);
    }

    public function test_travellers_from_past_trips_are_found_by_name_or_email(): void
    {
        $leader = User::factory()->create(['name' => 'Leader']);
        $rifat = User::factory()->create(['name' => 'Rifat Hasan', 'email' => 'rifat@example.com']);

        $trip = $this->tripFor($leader);
        $this->invite($trip, $leader, ['name' => 'Rifat Hasan', 'email' => 'rifat@example.com']);
        $this->invite($trip, $leader, ['name' => 'Nusrat Jahan', 'phone' => '01733333333']);

        $byName = $this->actingAs($leader)
            ->getJson(route('trips.past-travellers', ['q' => 'nusrat']))
            ->assertOk()
            ->json('travellers');

        $this->assertCount(1, $byName);
        $this->assertSame('Nusrat Jahan', $byName[0]['name']);
        $this->assertSame('+8801733333333', $byName[0]['phone']);
        $this->assertFalse($byName[0]['registered']);
        $this->assertSame('Sajek cloud trip', $byName[0]['last_trip']);

        $byEmail = $this->actingAs($leader)
            ->getJson(route('trips.past-travellers', ['q' => 'rifat@exa']))
            ->assertOk()
            ->json('travellers');

        $this->assertCount(1, $byEmail);
        $this->assertSame('rifat@example.com', $byEmail[0]['email']);
        $this->assertTrue($byEmail[0]['registered']);
    }

    public function test_an_empty_search_lists_recent_travellers_but_never_yourself(): void
    {
        $leader = User::factory()->create(['name' => 'Leader']);
        $trip = $this->tripFor($leader);

        $this->invite($trip, $leader, ['name' => 'Saiful']);
        $this->invite($trip, $leader, ['name' => 'Tania', 'email' => 'tania@example.com']);

        $names = collect(
            $this->actingAs($leader)->getJson(route('trips.past-travellers'))->assertOk()->json('travellers')
        )->pluck('name');

        $this->assertEqualsCanonicalizing(['Saiful', 'Tania'], $names->all());
        $this->assertNotContains('Leader', $names);
    }

    public function test_the_same_person_on_several_trips_shows_once(): void
    {
        $leader = User::factory()->create();
        $rifat = User::factory()->create(['name' => 'Rifat', 'email' => 'rifat@example.com', 'phone' => '+8801722222222']);

        $first = $this->tripFor($leader, 'Sylhet tea tour');
        $this->invite($first, $leader, ['name' => 'Rifat', 'phone' => '01722222222']);

        $second = $this->tripFor($leader, 'Cox\'s Bazar weekend');
        $this->invite($second, $leader, ['name' => 'Rifat H', 'email' => 'rifat@example.com']);

        $travellers = $this->actingAs($leader)
            ->getJson(route('trips.past-travellers', ['q' => 'rifat']))
            ->assertOk()
            ->json('travellers');

        $this->assertCount(1, $travellers);
        $this->assertSame('Rifat H', $travellers[0]['name']);
        $this->assertSame(2, $travellers[0]['trips']);
        $this->assertSame('Cox\'s Bazar weekend', $travellers[0]['last_trip']);
        // Each trip knew them by different details; both are kept.
        $this->assertSame('rifat@example.com', $travellers[0]['email']);
        $this->assertSame('+8801722222222', $travellers[0]['phone']);
    }

    public function test_travellers_on_somebody_elses_trip_stay_private(): void
    {
        $leader = User::factory()->create();
        $stranger = User::factory()->create();

        $theirs = $this->tripFor($stranger, 'Bandarban trek');
        $this->invite($theirs, $stranger, ['name' => 'Private Person', 'email' => 'private@example.com']);

        // Someone who left the leader's own trip is not offered either.
        $mine = $this->tripFor($leader);
        $this->invite($mine, $leader, ['name' => 'Gone Traveller', 'email' => 'gone@example.com']);
        $mine->members()->where('email', 'gone@example.com')->update(['status' => 'removed']);

        $this->actingAs($leader)
            ->getJson(route('trips.past-travellers'))
            ->assertOk()
            ->assertExactJson(['travellers' => []]);
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('trips.past-travellers', ['q' => 'a']))->assertUnauthorized();
    }

    public function test_a_picked_traveller_joins_the_new_trip_on_their_account(): void
    {
        $leader = User::factory()->create();
        $rifat = User::factory()->create(['name' => 'Rifat', 'email' => 'rifat@example.com']);

        $this->invite($this->tripFor($leader), $leader, ['name' => 'Rifat', 'email' => 'rifat@example.com']);

        $picked = $this->actingAs($leader)
            ->getJson(route('trips.past-travellers', ['q' => 'Rifat']))
            ->json('travellers.0');

        // The create form submits the picked traveller as an ordinary row.
        $this->actingAs($leader)
            ->post(route('trips.store'), [
                'title' => 'Sundarbans boat trip',
                'cover' => 'forest',
                'currency' => 'BDT',
                'members' => [
                    ['name' => $picked['name'], 'email' => $picked['email'], 'phone' => $picked['phone']],
                ],
            ])
            ->assertSessionHasNoErrors();

        $trip = Trip::where('title', 'Sundarbans boat trip')->firstOrFail();

        $this->assertTrue($trip->members()->where('user_id', $rifat->id)->where('status', 'active')->exists());
    }
}
