<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sofor is free: no trip limit, no trip pass, nothing to buy anywhere. */
class FreeForAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_traveller_creates_as_many_trips_as_they_like(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $n) {
            $this->actingAs($user)->get(route('trips.create'))->assertOk();
            $this->post(route('trips.store'), ['title' => 'Trip number '.$n, 'cover' => 'aurora', 'currency' => 'BDT'])
                ->assertRedirect(route('trips.show', Trip::latest('id')->first()));
        }

        $this->assertSame(6, Trip::where('user_id', $user->id)->count());
    }

    public function test_the_trip_pass_is_gone_everywhere(): void
    {
        $user = User::factory()->create();

        // Old links land on a new trip instead of a 404.
        $this->actingAs($user)->get('/pass')->assertRedirect('/trips/create');

        foreach ([route('dashboard'), route('trips.create'), route('profile.edit'), route('support.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Trip pass')->assertDontSee('Buy a pass')->assertDontSee('free trips');
        }

        auth()->logout();
        $this->get(route('home'))->assertOk()
            ->assertSee('Free. All of it, for everyone.')
            ->assertDontSee('Trip pass')
            ->assertDontSee('/ year');

        $this->assertFalse(Schema::hasTable('trip_passes'));
        $this->assertFalse(Schema::hasColumn('users', 'trips_created'));
    }
}
