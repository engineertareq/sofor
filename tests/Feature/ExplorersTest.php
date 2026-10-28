<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExplorerRanking;
use App\Services\TravelMaps;
use App\Support\ExplorerLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /explorers: public profiles ranked by how much of a map they have seen,
 * Bangladesh first, then worldwide.
 */
class ExplorersTest extends TestCase
{
    use RefreshDatabase;

    private function explorer(string $username, array $areas, string $map = 'bd', ?string $at = null, array $attributes = []): User
    {
        $user = User::factory()->create(['username' => $username, 'name' => ucfirst($username).' Traveller'] + $attributes);

        $this->travelTo($at ?? now());
        $maps = app(TravelMaps::class);
        $maps->add($user, $maps->get($map), $areas);
        $this->travelBack();

        return $user;
    }

    public function test_most_areas_rank_first_and_ties_go_to_whoever_got_there_first(): void
    {
        $this->explorer('late_three', ['BD-13', 'BD-11', 'BD-60'], at: '2026-03-01');
        $this->explorer('early_three', ['BD-13', 'BD-11', 'BD-01'], at: '2026-01-01');
        $this->explorer('one_only', ['BD-13'], at: '2025-01-01');

        $maps = app(TravelMaps::class);
        $order = app(ExplorerRanking::class)->standings($maps->get('bd'))
            ->map(fn (array $row) => User::find($row['user_id'])->username.'#'.$row['rank'].':'.$row['visited'])
            ->all();

        $this->assertSame(['early_three#1:3', 'late_three#2:3', 'one_only#3:1'], $order);
    }

    public function test_only_public_active_travellers_are_ranked(): void
    {
        $this->explorer('visible', ['BD-13']);
        $this->explorer('hidden', ['BD-13', 'BD-11'], attributes: ['profile_public' => false]);
        $this->explorer('paused_one', ['BD-13', 'BD-11'], attributes: ['is_active' => false]);
        $this->explorer('the_admin', ['BD-13', 'BD-11'], attributes: ['role' => 'admin']);
        // Territories show on maps but do not count as countries.
        $this->explorer('territory_only', ['HK'], 'world');

        $this->get(route('explorers'))
            ->assertOk()
            ->assertSee('@visible')
            ->assertDontSee('@hidden')
            ->assertDontSee('@paused_one')
            ->assertDontSee('@the_admin');

        $this->get(route('explorers', ['map' => 'world']))->assertOk()->assertDontSee('@territory_only');
    }

    public function test_the_board_opens_on_bangladesh_with_a_podium_then_the_list(): void
    {
        foreach (['ana' => 5, 'bob' => 4, 'cai' => 3, 'dev' => 2, 'eli' => 1] as $name => $count) {
            $this->explorer($name, array_map(fn (int $n) => sprintf('BD-%02d', $n), range(1, $count)));
        }
        $this->explorer('globetrotter', ['TH', 'NP', 'IN', 'MY'], 'world');

        $page = $this->get(route('explorers'))->assertOk();

        $page->assertSee('Explorers')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Top 3 explorers')
            ->assertSee('Gold, number 1: Ana Traveller, 5 districts')
            ->assertSee('The rest of the field')
            ->assertSee('start="4"', false)
            ->assertDontSee('@globetrotter');

        // Worldwide: the world map board instead.
        $this->get(route('explorers', ['map' => 'world']))
            ->assertOk()
            ->assertSee('Gold, number 1: Globetrotter Traveller, 4 countries')
            ->assertSee('data-area="TH"', false);

        // An unknown map falls back to Bangladesh.
        $this->get(route('explorers', ['map' => 'mars']))->assertOk()->assertSee('Ana Traveller');
    }

    public function test_searching_keeps_everyones_real_rank(): void
    {
        foreach (['ana' => 4, 'bob' => 3, 'zara' => 2] as $name => $count) {
            $this->explorer($name, array_map(fn (int $n) => sprintf('BD-%02d', $n), range(1, $count)));
        }

        $this->get(route('explorers', ['q' => '@zar']))
            ->assertOk()
            ->assertSee('Explorers matching')
            ->assertSee('start="3"', false)
            ->assertSee('@zara')
            ->assertDontSee('Top 3 explorers');

        $this->get(route('explorers', ['q' => 'nobody']))->assertOk()->assertSee('No explorer matches');
    }

    public function test_the_viewer_sees_where_they_stand_and_what_to_do_next(): void
    {
        $leader = $this->explorer('leader', ['BD-13', 'BD-11', 'BD-60']);
        $me = $this->explorer('me', ['BD-13']);

        $this->get(route('explorers'))->assertSee('Where would you rank?')->assertSee(route('register'), false);

        $this->actingAs($me)->get(route('explorers'))
            ->assertSee('You are #2 of 2')
            ->assertSee('Visit 3 more districts to pass')
            ->assertSee('@leader');
        $this->actingAs($leader)->get(route('explorers'))->assertSee('Nobody has seen more');

        $newcomer = User::factory()->create();
        $this->actingAs($newcomer)->get(route('explorers'))->assertSee('You are not on this board yet');

        $newcomer->forceFill(['profile_public' => false])->save();
        $this->actingAs($newcomer)->get(route('explorers'))->assertSee('Your profile is private');
    }

    public function test_the_community_map_shows_where_explorers_go(): void
    {
        $this->explorer('ana', ['BD-11', 'BD-13']);
        $this->explorer('bob', ['BD-11']);
        $this->explorer('cai', ['BD-11', 'BD-60']);

        $this->assertSame(
            ['BD-11' => 3, 'BD-13' => 1, 'BD-60' => 1],
            app(ExplorerRanking::class)->popularity(app(TravelMaps::class)->get('bd')),
        );

        $page = $this->get(route('explorers'))
            ->assertOk()
            ->assertSee('3 of 64 districts visited by at least one explorer')
            // Pointing at a district names it with its explorer count (resources/js/map-tips.js).
            ->assertSee('data-map-tips="plain"', false)
            ->assertSee('data-tip="No explorers yet"', false)
            // The busiest district glows brightest on the night map in the hero.
            ->assertSee('color-mix(in oklab, #f6b23a 100%', false)
            // Districts nobody has marked yet are an open invitation.
            ->assertSee('Still waiting for an explorer')
            ->assertSee('Nobody has marked these 61 districts yet. Be the first.');

        $this->assertMatchesRegularExpression('/data-area="BD-11" data-name="Cox&#039;s Bazar" data-group="Chattogram" data-tip="3 explorers"/', $page->getContent());
    }

    public function test_explorers_climb_levels_as_they_mark_more_of_the_map(): void
    {
        $this->assertSame('newcomer', ExplorerLevel::for(1, 64)['key']);
        $this->assertSame('voyager', ExplorerLevel::for(7, 64)['key']);
        $this->assertSame('trailblazer', ExplorerLevel::for(16, 64)['key']);
        $this->assertSame('pathfinder', ExplorerLevel::for(32, 64)['key']);
        $this->assertSame('legend', ExplorerLevel::for(48, 64)['key']);
        // The world is far bigger, so its levels come at fixed counts.
        $this->assertSame('voyager', ExplorerLevel::for(5, 195)['key']);
        $this->assertSame('legend', ExplorerLevel::for(80, 195)['key']);

        $this->explorer('ana', ['BD-11', 'BD-13', 'BD-60', 'BD-01', 'BD-56', 'BD-18', 'BD-22']);
        $this->explorer('bob', ['BD-11']);

        $this->get(route('explorers'))
            ->assertOk()
            ->assertSee('Explorer levels')
            ->assertSee('Voyager')
            ->assertSee('Newcomer');
    }

    public function test_long_boards_are_paged_and_a_board_with_nobody_says_so(): void
    {
        $this->get(route('explorers'))->assertOk()->assertSee('No explorers yet');

        foreach (range(1, 26) as $n) {
            $this->explorer('explorer_'.$n, ['BD-13']);
        }
        $this->get(route('explorers'))->assertOk()->assertSee('start="4"', false)->assertSee('page=2', false);
        $this->get(route('explorers', ['page' => 2]))->assertOk()->assertSee('start="24"', false)->assertDontSee('Top 3 explorers');
    }

    public function test_profiles_and_the_travel_map_show_the_rank(): void
    {
        $this->explorer('first', ['BD-13', 'BD-11']);
        $second = $this->explorer('second', ['BD-13']);

        $this->get('/@second')->assertOk()->assertSee('#2 explorer in Bangladesh');
        $this->actingAs($second)->get(route('travel.show', 'bd'))->assertOk()->assertSee('#2 on Explorers');
    }
}
