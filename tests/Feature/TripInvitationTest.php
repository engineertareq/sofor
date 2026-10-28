<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guard rails for the trip_members table, where trip_id + user_id is unique.
 * Inviting somebody who is already travelling must never throw a duplicate key
 * error (or silently attach the invite to the wrong account).
 */
class TripInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Trip, 1: User}
     */
    private function makeTrip(): array
    {
        $leader = User::factory()->create([
            'name' => 'Leader',
            'email' => 'leader@example.com',
            'phone' => '+8801711111111',
        ]);

        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        return [$trip->refresh(), $leader];
    }

    public function test_a_traveller_with_only_a_name_is_never_linked_to_another_account(): void
    {
        [$trip, $leader] = $this->makeTrip();

        $existing = User::factory()->create();

        $result = app(TripInvitationService::class)->invite($trip, $leader, ['name' => 'Saiful']);

        $this->assertTrue($result['created']);
        $this->assertFalse($result['registered']);
        $this->assertNull($result['member']->user_id);
        $this->assertSame('invited', $result['member']->status->value);

        // The row must not have been attached to some other account.
        $this->assertNotSame($existing->id, $result['member']->user_id);
        $this->assertSame(2, $trip->members()->count());
    }

    public function test_inviting_somebody_who_is_already_on_the_trip_refreshes_their_row(): void
    {
        [$trip, $leader] = $this->makeTrip();

        $traveler = User::factory()->create([
            'name' => 'Rifat',
            'email' => 'rifat@example.com',
            'phone' => '+8801722222222',
        ]);

        $invitations = app(TripInvitationService::class);

        $first = $invitations->invite($trip, $leader, ['name' => 'Rifat', 'email' => 'rifat@example.com']);
        $this->assertTrue($first['created']);

        // Same person, now invited by phone instead of email.
        $second = $invitations->invite($trip, $leader, ['name' => 'Rifat Hasan', 'phone' => '+8801722222222']);

        $this->assertFalse($second['created']);
        $this->assertSame($first['member']->id, $second['member']->id);
        $this->assertSame($traveler->id, $second['member']->user_id);
        $this->assertSame(2, $trip->members()->count());
    }

    public function test_adding_the_leader_as_a_traveller_is_reported_instead_of_crashing(): void
    {
        [$trip, $leader] = $this->makeTrip();

        $result = app(TripInvitationService::class)->invite($trip, $leader, [
            'name' => 'Leader',
            'email' => 'leader@example.com',
        ]);

        $this->assertFalse($result['created']);
        $this->assertTrue($result['self']);
        $this->assertStringContainsString('leads this trip', $result['message']);
        $this->assertSame(1, $trip->members()->count());
    }

    public function test_the_add_traveller_form_blocks_duplicates_with_a_validation_error(): void
    {
        [$trip, $leader] = $this->makeTrip();

        // The leader's own contact details.
        $this->actingAs($leader)
            ->post(route('trips.members.store', $trip), ['name' => 'Leader', 'email' => 'leader@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $trip->members()->count());
        $this->assertSame(0, TripMember::where('role', 'member')->count());

        // Then somebody who is already travelling by phone.
        $this->actingAs($leader)
            ->post(route('trips.members.store', $trip), ['name' => 'Nusrat', 'phone' => '01733333333'])
            ->assertSessionHasNoErrors();

        $this->actingAs($leader)
            ->post(route('trips.members.store', $trip), ['name' => 'Nusrat Jahan', 'phone' => '+8801733333333'])
            ->assertSessionHasErrors('email');

        $this->assertSame(2, $trip->members()->count());
    }

    public function test_creating_a_trip_with_the_leaders_own_contact_is_rejected(): void
    {
        $leader = User::factory()->create([
            'name' => 'Leader',
            'email' => 'leader@example.com',
            'phone' => '+8801711111111',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($leader)
            ->post(route('trips.store'), [
                'title' => 'Sajek cloud trip',
                'cover' => 'violet',
                'currency' => 'BDT',
                'members' => [
                    ['name' => 'Me', 'phone' => '01711111111'],
                ],
            ])
            ->assertSessionHasErrors('members.0.email');

        $this->assertSame(0, Trip::count());
    }

    public function test_creating_a_trip_with_a_name_only_traveller_works(): void
    {
        $leader = User::factory()->create();

        $this->actingAs($leader)
            ->post(route('trips.store'), [
                'title' => 'Sylhet tea tour',
                'cover' => 'forest',
                'currency' => 'BDT',
                'members' => [
                    ['name' => 'Saiful'],
                    ['name' => 'Ruma', 'email' => 'ruma@example.com'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $trip = Trip::firstOrFail();

        $this->assertSame(3, $trip->members()->count());
        // Neither traveller has an account yet, so both wait as pending invites.
        $this->assertSame(2, $trip->members()->whereNull('user_id')->count());
    }

    public function test_the_same_traveller_cannot_slip_through_as_two_rows_in_one_submission(): void
    {
        [$trip, $leader] = $this->makeTrip();

        $traveler = User::factory()->create([
            'email' => 'rifat@example.com',
            'phone' => '+8801722222222',
        ]);

        $invitations = app(TripInvitationService::class);

        $invitations->invite($trip, $leader, ['name' => 'Rifat', 'email' => 'rifat@example.com']);
        $invitations->invite($trip, $leader, ['name' => 'Rifat H', 'phone' => '+8801722222222']);

        $this->assertSame(2, $trip->members()->count());
        $this->assertSame(1, $trip->members()->where('user_id', $traveler->id)->count());
    }

    public function test_joining_by_code_when_already_on_the_trip_does_not_duplicate(): void
    {
        [$trip, $leader] = $this->makeTrip();

        $invitations = app(TripInvitationService::class);

        // Invited by phone, then the same person registers and claims the invite.
        $invitations->invite($trip, $leader, ['name' => 'Nusrat', 'phone' => '01733333333']);

        $traveler = User::factory()->create(['phone' => '+8801733333333']);

        $this->actingAs($traveler)
            ->post(route('trips.join', $trip->invite_code))
            ->assertRedirect(route('trips.show', $trip));

        $this->assertSame(2, $trip->members()->count());
        $this->assertSame(1, $trip->members()->where('user_id', $traveler->id)->count());
    }
}
