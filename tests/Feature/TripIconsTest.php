<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use App\Support\Glyphs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sofor draws its own icons instead of emoji, which every phone renders differently. */
class TripIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_trip_takes_one_of_the_icons(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('trips.store'), [
            'title' => 'Sajek', 'cover' => 'aurora', 'currency' => 'BDT', 'icon' => 'hills',
        ])->assertSessionHasNoErrors();

        $trip = Trip::latest('id')->firstOrFail();
        $this->assertSame('hills', $trip->icon);

        $this->put(route('trips.update', $trip), ['title' => 'Sajek', 'cover' => 'aurora', 'currency' => 'BDT', 'icon' => 'boat'])
            ->assertSessionHasNoErrors();
        $this->assertSame('boat', $trip->refresh()->icon);

        // Only the drawn icons are accepted - no free-typed emoji.
        $this->put(route('trips.update', $trip), ['title' => 'Sajek', 'cover' => 'aurora', 'currency' => 'BDT', 'icon' => '🚀'])
            ->assertSessionHasErrors('icon');

        // A form without the field keeps the icon, a new trip without one gets the compass.
        $this->put(route('trips.update', $trip), ['title' => 'Sajek', 'cover' => 'aurora', 'currency' => 'BDT'])->assertSessionHasNoErrors();
        $this->assertSame('boat', $trip->refresh()->icon);
        $this->post(route('trips.store'), ['title' => 'Plain', 'cover' => 'aurora', 'currency' => 'BDT']);
        $this->assertSame('compass', Trip::latest('id')->firstOrFail()->icon);
    }

    public function test_trips_made_with_an_emoji_show_the_matching_icon(): void
    {
        $this->assertSame('beach', Glyphs::forTrip('🏖️'));
        $this->assertSame('hills', Glyphs::forTrip('⛰️'));
        $this->assertSame('tea', Glyphs::forTrip('🍃'));
        $this->assertSame('compass', Glyphs::forTrip('🦄'));   // anything else falls back to the compass
        $this->assertSame('compass', Glyphs::forTrip(null));

        $this->assertSame('roadtrip', Trip::factory()->make(['emoji' => '🚐'])->icon);
    }

    public function test_pages_draw_icons_and_no_emoji(): void
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'emoji' => '🏖️']);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Dinner', 'amount' => 1200, 'category' => 'food', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->value('id'), 'split_type' => 'equal',
        ])->assertRedirect();

        foreach ([route('dashboard'), route('trips.index'), route('trips.show', $trip), route('trips.create'), route('trips.edit', $trip), route('home')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('viewBox="0 0 40 40"', $html, $url);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', $html, "Emoji left on $url");
        }
    }
}
