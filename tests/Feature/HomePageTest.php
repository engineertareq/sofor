<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TravelMaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page: real map, real Explorers (public profiles only), and a
 * way in that suits whoever is looking.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function explorer(string $username, int $districts, array $attributes = []): User
    {
        $user = User::factory()->create(['username' => $username, 'name' => ucfirst($username).' Rahman'] + $attributes);
        $maps = app(TravelMaps::class);
        $maps->add($user, $maps->get('bd'), array_map(fn (int $n) => sprintf('BD-%02d', $n), range(1, $districts)));

        return $user;
    }

    public function test_the_hero_draws_a_real_route_on_the_real_map(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Happy trips')
            ->assertSee('Tidy accounts')
            ->assertSee('heroJourney', false)
            ->assertSee('data-area="BD-11"', false)
            ->assertSee('data-stop="6"', false)
            ->assertSee('id="features"', false)
            ->assertSee('id="how"', false)
            ->assertSee('id="free"', false)
            ->assertSee('id="faq"', false);
    }

    public function test_explorer_proof_appears_only_once_there_is_something_real_to_show(): void
    {
        $this->explorer('ana', 5);
        $this->explorer('bob', 3);

        // Two explorers so far: both on the board, the third place an open seat.
        $this->get(route('home'))->assertOk()
            ->assertDontSee('explorers</strong>', false)
            ->assertSee(route('profile.public', 'ana'), false)
            ->assertSee('Your spot is open')
            ->assertSee('districts lit up');

        $this->explorer('cai', 2);
        $this->explorer('dev', 9, ['profile_public' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('3 explorers</strong>', false)
            ->assertSee('Top explorers right now')
            ->assertSee(route('profile.public', 'ana'), false)
            ->assertDontSee(route('profile.public', 'dev'), false);
    }

    public function test_the_way_in_matches_who_is_looking(): void
    {
        $this->get(route('home'))->assertSee('Start a trip - it is free')->assertSee(route('register'), false);

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertSee('Start a trip')
            ->assertSee(route('trips.create'), false)
            ->assertDontSee('Open my trips');

        $this->actingAs(User::factory()->admin()->create())->get(route('home'))->assertSee('Open the console');
    }
}
