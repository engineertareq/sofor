<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitedPlace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /travel-map: anyone can mark where they have been and download the picture. */
class MapMakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_makes_a_map_without_an_account(): void
    {
        $this->get(route('map-maker'))
            ->assertOk()
            ->assertSee('Make your travel map')
            ->assertSee('Download image')
            ->assertSee($this->embedded('local', 'true'), false)        // the marks stay in the browser
            ->assertSee($this->embedded('store', 'null'), false)        // nothing is sent anywhere
            ->assertSee('Keep your map for good')
            ->assertSee('data-map-tips="state"', false)                // labels say "tap to mark" or "visited"
            ->assertSee('data-map-interactive', false)
            ->assertSee('data-area="BD-13" data-name="Dhaka" data-group="Dhaka"', false);

        $this->get(route('map-maker', 'world'))->assertOk()->assertSee('data-area="IN"', false)->assertSee('All countries');
        $this->get('/travel-map/mars')->assertNotFound();
    }

    public function test_a_signed_in_traveller_sees_and_edits_their_saved_map(): void
    {
        $traveller = User::factory()->create();
        VisitedPlace::query()->create(['user_id' => $traveller->id, 'map' => 'bd', 'area' => 'BD-13']);
        VisitedPlace::query()->create(['user_id' => $traveller->id, 'map' => 'world', 'area' => 'IN']);

        $this->actingAs($traveller)
            ->get(route('map-maker'))
            ->assertOk()
            ->assertSee('This is your saved travel map')
            ->assertSee($this->embedded('local', 'false'), false)
            ->assertSee($this->embedded('store', chr(92).'u0022http'), false)   // taps save to the account
            ->assertSee('class="tm-area is-visited"', false)                      // Dhaka, already on their map
            ->assertDontSee('Keep your map for good');

        $this->assertMatchesRegularExpression('/data-area="IN"[^>]*class="tm-area is-visited"/', $this->get(route('map-maker', 'world'))->assertOk()->getContent());

        // Places marked before signing in are offered on their own map too.
        $this->get(route('travel.show', 'bd'))->assertOk()->assertSee('Add them');
    }

    public function test_unconfirmed_and_console_accounts_keep_the_map_in_the_browser(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('map-maker'))
            ->assertOk()
            ->assertSee('Confirm your account to save this map')
            ->assertSee($this->embedded('local', 'true'), false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('map-maker'))
            ->assertOk()
            ->assertSee($this->embedded('local', 'true'), false)
            ->assertDontSee('Keep your map for good')
            ->assertDontSee('This is your saved travel map');
    }

    /** How a setting looks inside the page's Js::from() payload, where quotes are written ". */
    private function embedded(string $key, string $value): string
    {
        $quote = chr(92).'u0022';

        return $quote.$key.$quote.':'.$value;
    }

    public function test_the_map_maker_is_in_the_menu_and_the_sitemap(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('href="'.route('map-maker').'"', false);
        $this->get(route('sitemap'))->assertOk()->assertSee(route('map-maker'));
    }
}
