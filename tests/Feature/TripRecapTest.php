<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shareable recap card: the trip in numbers, with no names on it. */
class TripRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recap_card_shows_the_numbers_and_no_names(): void
    {
        $leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $friend = User::factory()->create(['name' => 'Asha Rahman']);
        $trip = Trip::factory()->create([
            'user_id' => $leader->id, 'title' => 'Sajek & Khagrachari <cloud> weekend with the whole gang',
            'destination' => 'Sajek Valley', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03',
        ]);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->invite($trip, $leader, ['name' => $friend->name, 'email' => $friend->email]);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cottage for two nights', 'amount' => 6000, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->where('user_id', $leader->id)->value('id'), 'split_type' => 'equal',
            'participant_ids' => $trip->members()->pluck('id')->all(),
        ]);

        $response = $this->get(route('trips.recap', $trip))->assertOk();

        preg_match('#<svg xmlns.*?</svg>#s', $response->getContent(), $match);
        $svg = simplexml_load_string($match[0]);
        $this->assertNotFalse($svg, 'The recap card must be well-formed SVG to be drawn onto a canvas.');

        $text = strip_tags($match[0]);
        $this->assertStringContainsString('৳6,000', $text);
        $this->assertStringContainsString('৳3,000', $text);       // per person
        $this->assertStringContainsString('৳1,000', $text);       // a day each, over three days
        $this->assertStringContainsString('Cottage for two nights', $text);
        $this->assertStringNotContainsString('Rifat', $text);
        $this->assertStringNotContainsString('Asha', $text);

        // The poster's map lights up where the trip went (Sajek is in Rangamati, and the
        // title names Khagrachari) and names it; what is still owed stays off a picture
        // made for posting.
        $this->assertMatchesRegularExpression('/Khagrachari|Rangamati/', $text);
        $this->assertSame(2, substr_count($match[0], 'fill="#f6b23a" stroke="#0d1715"'));
        $this->assertStringNotContainsString('still to settle', $text);

        $this->actingAs(User::factory()->create())->get(route('trips.recap', $trip))->assertForbidden();
    }
}
