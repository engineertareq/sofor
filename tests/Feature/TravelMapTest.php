<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\VisitedPlace;
use App\Services\TravelMaps;
use App\Services\TravelSuggestions;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The travel map: Bangladesh by district, the world by country, filled in by
 * hand or from the traveller's trips, and built so more maps can be added.
 */
class TravelMapTest extends TestCase
{
    use RefreshDatabase;

    private function maps(): TravelMaps
    {
        return app(TravelMaps::class);
    }

    private function trip(User $owner, array $attributes): Trip
    {
        $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => 'BDT', 'description' => null] + $attributes);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $owner);

        return $trip;
    }

    public function test_the_map_data_is_complete_and_every_place_points_somewhere_real(): void
    {
        $bd = $this->maps()->get('bd');
        $expected = array_map(fn (int $n) => sprintf('BD-%02d', $n), range(1, 64));

        $this->assertEqualsCanonicalizing($expected, array_keys($bd->areas()));
        $this->assertCount(8, $bd->groups());
        $this->assertSame("Cox's Bazar", $bd->name('BD-11'));
        $this->assertSame('BD', $bd->country());

        $world = $this->maps()->world();
        $this->assertSame(195, $world->countedTotal());
        $this->assertTrue($world->has('BD') && $world->has('SG') && $world->has('MV'));
        $this->assertFalse($world->isCounted('HK'), 'Territories do not count towards the 195.');

        foreach ($this->maps()->keys() as $key) {
            $map = $this->maps()->get($key);

            foreach ($map->areas() as $id => $area) {
                $this->assertArrayHasKey($area['group'], $map->groups(), "$key: $id has an unknown group");
                $this->assertTrue($area['d'] !== '' || ($area['tiny'] ?? false), "$key: $id has nothing to draw");
            }

            $places = resource_path("maps/places/$key.php");

            foreach (is_file($places) ? require $places : [] as $phrase => $areas) {
                foreach ((array) $areas as $id) {
                    $this->assertTrue($map->has($id), "$key places: '$phrase' points to unknown area $id");
                }
            }
        }
    }

    public function test_travellers_get_a_tab_per_map_and_admins_stay_in_the_console(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->get(route('travel.index'))->assertRedirect(route('travel.show', 'bd'));

        $this->actingAs($traveller)->get(route('travel.show', 'bd'))
            ->assertOk()
            ->assertSee('data-area="BD-13"', false)
            ->assertSee(route('travel.show', 'world'), false)
            ->assertSee('Download image');

        $this->actingAs($traveller)->get(route('travel.show', 'world'))
            ->assertOk()
            ->assertSee('data-area="TH"', false);

        $this->actingAs($traveller)->get('/map/mars')->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('travel.show', 'bd'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_marking_a_district_also_marks_bangladesh_on_the_world_map(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)
            ->postJson(route('travel.places.store', 'bd'), ['areas' => ['BD-13']])
            ->assertOk()
            ->assertJsonPath('visited', ['BD-13'])
            ->assertJsonPath('country', 'BD')
            ->assertJsonPath('stats.visited', 1)
            ->assertJsonPath('stats.total', 64);

        $this->assertSame(['BD'], $this->maps()->visited($traveller, $this->maps()->world()));

        // Again: nothing new, nothing doubled.
        $this->actingAs($traveller)
            ->postJson(route('travel.places.store', 'bd'), ['areas' => ['BD-13']])
            ->assertOk()
            ->assertJsonPath('added', [])
            ->assertJsonPath('message', 'Already on your map.');

        $this->assertSame(2, VisitedPlace::count());
    }

    public function test_only_real_areas_of_that_map_are_accepted(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->postJson(route('travel.places.store', 'bd'), ['areas' => ['BD-99']])->assertStatus(422);
        $this->actingAs($traveller)->postJson(route('travel.places.store', 'bd'), ['areas' => ['TH']])->assertStatus(422);
        $this->actingAs($traveller)->postJson(route('travel.places.store', 'bd'), ['areas' => []])->assertStatus(422);

        $this->assertSame(0, VisitedPlace::count());
    }

    public function test_places_can_be_removed_one_at_a_time_or_all_at_once(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->postJson(route('travel.places.store', 'bd'), ['areas' => ['BD-13', 'BD-60', 'BD-11']]);

        $this->actingAs($traveller)
            ->deleteJson(route('travel.places.destroy', ['bd', 'BD-60']))
            ->assertOk()
            ->assertJsonPath('stats.visited', 2);

        $this->actingAs($traveller)
            ->from(route('travel.show', 'bd'))
            ->delete(route('travel.places.clear', 'bd'))
            ->assertRedirect(route('travel.show', 'bd'));

        $this->assertSame([], $this->maps()->visited($traveller, $this->maps()->get('bd')));
        // Clearing Bangladesh leaves the world map alone.
        $this->assertSame(['BD'], $this->maps()->visited($traveller, $this->maps()->world()));
    }

    public function test_territories_show_on_the_map_but_do_not_count_as_countries(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)
            ->postJson(route('travel.places.store', 'world'), ['areas' => ['HK', 'TH']])
            ->assertOk()
            ->assertJsonPath('stats.visited', 1)
            ->assertJsonPath('stats.extra', 1)
            ->assertJsonPath('stats.total', 195)
            ->assertJsonPath('stats.groups_visited', 1);
    }

    public function test_trips_suggest_districts_and_countries(): void
    {
        $traveller = User::factory()->create();
        $stranger = User::factory()->create();

        $this->trip($traveller, ['title' => "Cox's Bazar weekend", 'destination' => 'Sajek Valley, Rangamati']);
        $this->trip($traveller, ['title' => 'শ্রীমঙ্গল চা বাগান ভ্রমণ', 'destination' => null]);
        $this->trip($traveller, ['title' => 'Family visit', 'destination' => 'Tungipara, Gopalgonj']);
        $this->trip($traveller, ['title' => 'Bangkok and Pattaya', 'destination' => null, 'currency' => 'THB']);
        // Somebody else's trip says nothing about this traveller.
        $this->trip($stranger, ['title' => 'Jaflong day out', 'destination' => 'Sylhet']);

        $suggestions = app(TravelSuggestions::class);

        $bd = collect($suggestions->forUser($traveller, $this->maps()->get('bd')))->pluck('id');
        $this->assertEqualsCanonicalizing(['BD-11', 'BD-56', 'BD-38', 'BD-17'], $bd->all());

        $world = collect($suggestions->forUser($traveller, $this->maps()->world()))->pluck('id');
        $this->assertEqualsCanonicalizing(['BD', 'TH'], $world->all());

        // Already on the map: no longer suggested.
        $this->maps()->add($traveller, $this->maps()->get('bd'), ['BD-11']);
        $bd = collect($suggestions->forUser($traveller, $this->maps()->get('bd')))->pluck('id');
        $this->assertNotContains('BD-11', $bd);

        $this->actingAs($traveller)->get(route('travel.show', 'bd'))
            ->assertOk()
            ->assertSee('From your trips');
    }

    public function test_the_trip_page_offers_to_add_its_places_and_credits_the_trip(): void
    {
        $traveller = User::factory()->create();
        $trip = $this->trip($traveller, ['title' => "Cox's Bazar weekend", 'destination' => 'Inani beach, Teknaf']);

        $this->actingAs($traveller)->get(route('trips.show', $trip))
            ->assertOk()
            ->assertSee('Your travel map:')
            ->assertSee(route('travel.from-trip', $trip), false);

        $this->actingAs($traveller)
            ->from(route('trips.show', $trip))
            ->post(route('travel.from-trip', $trip))
            ->assertRedirect(route('trips.show', $trip))
            ->assertSessionHas('status', "Added Cox's Bazar and Bangladesh to your travel map.");

        $this->assertSame(
            ['BD-11' => $trip->id, 'BD' => $trip->id],
            VisitedPlace::query()->where('user_id', $traveller->id)->pluck('trip_id', 'area')->all(),
        );

        $this->actingAs($traveller)->get(route('trips.show', $trip))->assertDontSee('Your travel map:');
    }

    public function test_a_visit_is_only_credited_to_a_trip_the_traveller_is_on(): void
    {
        $traveller = User::factory()->create();
        $theirs = $this->trip(User::factory()->create(), ['title' => 'Not mine']);

        $this->actingAs($traveller)->postJson(route('travel.places.store', 'world'), ['areas' => ['NP'], 'trip' => $theirs->id])->assertOk();

        $this->assertNull(VisitedPlace::query()->where('area', 'NP')->value('trip_id'));

        $this->actingAs($traveller)->post(route('travel.from-trip', $theirs))->assertForbidden();
    }
}
