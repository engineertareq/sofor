<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripActivity;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConfirmsEmail;
use Tests\TestCase;

/**
 * Travelers arrive through an invite link, then sign in or register from that
 * page. They must land inside the trip immediately, exactly once.
 */
class ReferralLinkTest extends TestCase
{
    use ConfirmsEmail, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "confirm your email" codes are read from here.
        Notification::fake();
    }

    private function makeTrip(): Trip
    {
        $leader = User::factory()->create(['name' => 'Leader']);

        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => 'Sajek cloud trip']);

        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        return $trip->refresh();
    }

    public function test_a_guest_from_an_invite_link_joins_as_soon_as_their_email_is_confirmed(): void
    {
        $trip = $this->makeTrip();

        $this->get(route('trips.join.show', $trip->invite_code))->assertOk()->assertSee($trip->title);
        $this->assertSame($trip->invite_code, session(\App\Http\Controllers\TripJoinController::SESSION_KEY));

        $this->post(route('register.store'), [
            'name' => 'Nusrat Jahan',
            'username' => 'nusrat',
            'contact_type' => 'email',
            'email' => 'nusrat@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'nusrat@example.com')->firstOrFail();

        // Not in the trip until the account is confirmed; the invite waits in the session.
        $this->assertSame(0, $trip->members()->where('user_id', $user->id)->count());
        $this->get(route('trips.show', $trip))->assertRedirect(route('verification.notice'));

        $this->confirmEmail('nusrat@example.com')->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // And the trip is reachable straight away.
        $this->actingAs($user->refresh())->get(route('trips.show', $trip))->assertOk();
    }

    public function test_a_guest_from_an_invite_link_joins_immediately_after_logging_in(): void
    {
        $trip = $this->makeTrip();

        $traveler = User::factory()->create([
            'email' => 'rifat@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->get(route('trips.join.show', $trip->invite_code))->assertOk();

        $this->post(route('login.store'), [
            'login' => 'rifat@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $traveler->id,
            'status' => 'active',
        ]);
    }

    public function test_registering_from_an_invite_link_twice_never_duplicates_the_traveller(): void
    {
        $trip = $this->makeTrip();

        $this->get(route('trips.join.show', $trip->invite_code));

        $this->post(route('register.store'), [
            'name' => 'Nusrat Jahan',
            'username' => 'nusrat',
            'contact_type' => 'email',
            'email' => 'nusrat@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->confirmEmail('nusrat@example.com')->assertRedirect(route('trips.show', $trip));

        $user = User::where('email', 'nusrat@example.com')->firstOrFail();

        // Visiting the link again, and posting the join form again, must not add
        // a second traveller row.
        $this->actingAs($user)->get(route('trips.join.show', $trip->invite_code))
            ->assertRedirect(route('trips.show', $trip));

        $this->actingAs($user)->post(route('trips.join', $trip->invite_code))
            ->assertRedirect(route('trips.show', $trip));

        $this->assertSame(1, $trip->members()->where('user_id', $user->id)->count());
        $this->assertSame(1, TripActivity::where('trip_id', $trip->id)->where('type', 'member_joined')->count());
    }

    public function test_a_traveller_invited_by_phone_joins_once_when_the_link_is_used(): void
    {
        $trip = $this->makeTrip();

        app(TripInvitationService::class)->invite($trip->fresh(), $trip->owner, [
            'name' => 'Nusrat',
            'phone' => '01733333333',
        ]);

        $traveler = User::factory()->create(['phone' => '+8801733333333']);

        // The invite link is remembered, then the traveller logs in.
        $this->get(route('trips.join.show', $trip->invite_code));

        $this->post(route('login.store'), [
            'login' => '01733333333',
            'password' => 'password',
        ])->assertRedirect(route('trips.show', $trip));

        $this->assertSame(2, $trip->members()->count());
        $this->assertSame(1, $trip->members()->where('user_id', $traveler->id)->count());
        $this->assertSame(1, TripActivity::where('trip_id', $trip->id)->where('type', 'member_joined')->count());
    }

    public function test_an_invite_link_for_an_ended_trip_shows_the_preview_without_joining(): void
    {
        $trip = $this->makeTrip();
        $trip->forceFill(['status' => 'ended', 'ended_at' => now()])->save();

        $this->get(route('trips.join.show', $trip->invite_code))->assertOk();

        $this->post(route('register.store'), [
            'name' => 'Late Traveller',
            'username' => 'late_traveller',
            'contact_type' => 'email',
            'email' => 'late@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->confirmEmail('late@example.com')->assertRedirect(route('trips.join.show', $trip->invite_code));

        $user = User::where('email', 'late@example.com')->firstOrFail();

        $this->assertSame(0, $trip->members()->where('user_id', $user->id)->count());
    }

    public function test_a_stale_or_unknown_invite_code_falls_back_to_the_dashboard(): void
    {
        // Somebody else registered first, so this is an ordinary traveller account.
        User::factory()->admin()->create();

        $this->withSession([\App\Http\Controllers\TripJoinController::SESSION_KEY => 'NOPE1234']);

        $this->post(route('register.store'), [
            'name' => 'Someone',
            'username' => 'someone',
            'contact_type' => 'email',
            'email' => 'someone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->confirmEmail('someone@example.com')->assertRedirect(route('dashboard'));

        $this->assertSame(2, User::count());
    }

    public function test_the_auth_screens_name_the_trip_that_is_waiting(): void
    {
        $trip = $this->makeTrip();

        $this->get(route('trips.join.show', $trip->invite_code));

        $this->get(route('register'))->assertOk()->assertSee('You are joining Sajek cloud trip', false);
        $this->get(route('login'))->assertOk()->assertSee('You are joining Sajek cloud trip', false);
    }
}
