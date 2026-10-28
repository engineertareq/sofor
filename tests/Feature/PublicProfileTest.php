<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TravelMaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * sofor.io/@username: name, photo and travel maps for anybody with the link,
 * and nothing private.
 */
class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private function traveller(array $attributes = []): User
    {
        return User::factory()->create([
            'name' => 'Rifat Hasan',
            'username' => 'rifat',
            'email' => 'rifat@example.com',
            'phone' => '+8801711111111',
        ] + $attributes);
    }

    private function visit(User $user, string $map, array $areas): void
    {
        $maps = app(TravelMaps::class);
        $maps->add($user, $maps->get($map), $areas);
    }

    public function test_anyone_can_see_a_travellers_maps_but_not_their_contact_details(): void
    {
        $user = $this->traveller();
        $this->visit($user, 'bd', ['BD-13', 'BD-11']);
        $this->visit($user, 'world', ['TH']);

        $this->get('/@rifat')
            ->assertOk()
            ->assertSee('Rifat Hasan')
            ->assertSee('@rifat')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('class="tm-area is-visited"', false)
            ->assertSee("Cox's Bazar")
            ->assertSee('2 districts visited')
            ->assertDontSee('rifat@example.com')
            ->assertDontSee('01711111111')
            ->assertDontSee('1711111111');

        $this->get('/@rifat?map=world')
            ->assertOk()
            ->assertSee('data-area="TH"', false)
            ->assertSee('2 countries visited'); // Thailand, plus Bangladesh from the districts
    }

    public function test_the_address_is_case_insensitive_and_unknown_or_hidden_accounts_are_not_found(): void
    {
        $this->traveller();

        $this->get('/@Rifat')->assertRedirect('/@rifat')->assertStatus(301);
        $this->get('/@nobody')->assertNotFound()->assertSee('This page took a wrong turn');

        User::factory()->admin()->create(['username' => 'opsadmin']);
        $this->get('/@opsadmin')->assertNotFound();

        User::factory()->paused()->create(['username' => 'paused_one']);
        $this->get('/@paused_one')->assertNotFound();
    }

    public function test_a_private_profile_shows_nothing_but_its_owner_still_sees_it(): void
    {
        $user = $this->traveller(['profile_public' => false]);
        $this->visit($user, 'bd', ['BD-13']);

        $this->get('/@rifat')
            ->assertOk()
            ->assertSee('This profile is private')
            ->assertDontSee('Rifat Hasan')
            ->assertDontSee('data-area', false);

        $this->actingAs($user)->get('/@rifat')
            ->assertOk()
            ->assertSee('Only you can see this page')
            ->assertSee('data-area="BD-13"', false);
    }

    public function test_an_empty_map_says_so_and_points_the_owner_to_fill_it(): void
    {
        $user = $this->traveller();

        $this->get('/@rifat')->assertOk()->assertSee('Rifat has not added any places yet');

        $this->actingAs($user)->get('/@rifat')
            ->assertOk()
            ->assertSee('Your map is still empty')
            ->assertSee(route('travel.index'), false);
    }

    public function test_the_page_shows_the_level_the_board_and_each_division(): void
    {
        $user = $this->traveller();
        $this->visit($user, 'bd', ['BD-13', 'BD-11']);   // Dhaka, Cox's Bazar: 2 of 64

        $this->get('/@rifat')
            ->assertOk()
            ->assertSee('tm-night', false)                                    // their map under the night sky
            ->assertSee('stroke-dasharray="3 100"', false)                   // the ring: 3% explored
            ->assertSee('Newcomer · Sofor explorer')
            ->assertSee('5 more districts to Voyager')                       // Voyager starts at 7
            ->assertSee('#1 explorer in Bangladesh')
            ->assertSee('On the Explorers board in Bangladesh')
            ->assertSee('Division by division')
            ->assertSee('waffle-tile size-3.5 sm:size-4 is-on', false)
            // Everyone who is not the owner is invited to make their own.
            ->assertSee('How many districts have you visited?')
            ->assertSee('href="'.route('map-maker').'"', false)
            ->assertDontSee('Edit profile');

        $this->actingAs($user)->get('/@rifat')
            ->assertSee('Edit profile')
            ->assertSee(route('travel.show', 'bd'), false)
            ->assertDontSee('How many districts have you visited?');
    }

    public function test_another_traveller_sees_what_the_two_of_them_share(): void
    {
        $user = $this->traveller();
        $this->visit($user, 'bd', ['BD-13', 'BD-11', 'BD-60']);

        $friend = User::factory()->create(['name' => 'Nadia Sultana', 'username' => 'nadia']);
        $this->visit($friend, 'bd', ['BD-13', 'BD-64']);

        $this->actingAs($friend)->get('/@rifat')
            ->assertOk()
            ->assertSee('You and Rifat')
            ->assertSee('1 district in common')
            ->assertSee('Only Rifat')
            ->assertSee('Only you')
            ->assertDontSee('How many districts have you visited?');

        // Signed out, there is nobody to compare with.
        auth()->logout();
        $this->get('/@rifat')->assertDontSee('You and Rifat');
    }

    public function test_the_travel_map_page_links_to_the_public_profile(): void
    {
        $user = $this->traveller();

        $this->actingAs($user)->get(route('travel.show', 'bd'))
            ->assertOk()
            ->assertSee('Shown on your public profile')
            ->assertSee($user->publicUrl(), false);

        $user->forceFill(['profile_public' => false])->save();

        $this->actingAs($user)->get(route('travel.show', 'bd'))->assertSee('Your profile is private');
    }
}
