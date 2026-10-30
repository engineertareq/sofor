<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin accounts live in the console and travellers live in the app. Neither
 * side can wander into the other.
 */
class AdminSeparationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The page a browser remembers to return to belongs to whoever used it last.
     * Signing in only goes back to it when it is a page for this kind of account.
     */
    public function test_signing_in_never_lands_a_traveller_in_the_console_or_an_admin_in_the_app(): void
    {
        $traveller = User::factory()->create(['email' => 'rafi@example.com', 'password' => 'password123']);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'password123']);
        $signIn = fn (string $email, string $remembered) => $this->withSession(['url.intended' => $remembered])
            ->post(route('login.store'), ['login' => $email, 'password' => 'password123']);

        // An admin page left behind by an admin who signed out.
        $signIn('rafi@example.com', url('/admin/users'))->assertRedirect(route('dashboard'));
        auth()->logout();

        // A traveller's own page is still where they meant to go.
        $signIn('rafi@example.com', url('/balances'))->assertRedirect(url('/balances'));
        auth()->logout();

        $signIn('admin@example.com', url('/balances'))->assertRedirect(route('admin.dashboard'));
        auth()->logout();

        $signIn('admin@example.com', url('/admin/users'))->assertRedirect(url('/admin/users'));
        auth()->logout();

        // Nothing remembered, or somewhere off the site: home.
        $signIn('rafi@example.com', 'https://evil.example/admin')->assertRedirect(route('dashboard'));
    }

    /** @return array{0: Trip, 1: User} */
    private function tripWithACost(): array
    {
        $leader = User::factory()->create(['name' => 'Rifat Leader']);
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => 'Sajek cloud trip']);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->invite($trip, $leader, ['name' => 'Nusrat Jahan']);

        $members = $trip->members()->get();

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Hill cottage',
            'amount' => 4000,
            'category' => 'accommodation',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ])->assertRedirect();

        // Each test decides who is signed in next.
        auth()->logout();

        return [$trip, $leader];
    }

    public function test_signing_in_takes_each_account_to_its_own_side(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);
        User::factory()->create(['email' => 'rifat@example.com']);

        $this->post(route('login.store'), ['login' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->post(route('logout'));

        $this->post(route('login.store'), ['login' => 'rifat@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_an_admin_is_sent_to_the_console_from_every_traveller_page(): void
    {
        [$trip] = $this->tripWithACost();
        $admin = User::factory()->admin()->create();

        foreach ([
            route('dashboard'),
            route('trips.index'),
            route('trips.create'),
            route('trips.show', $trip),
            route('trips.summary', $trip),
            route('notifications.index'),
            route('profile.edit'),
            route('login'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertRedirect(route('admin.dashboard'));
        }

        $this->actingAs($admin)->post(route('trips.store'), [
            'title' => 'Admin trip', 'cover' => 'aurora', 'currency' => 'BDT',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)->getJson(route('trips.past-travellers'))->assertForbidden();

        $this->assertSame(1, Trip::count());
    }

    public function test_travellers_still_cannot_open_the_console(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($traveller)->get(route('admin.profile'))->assertForbidden();
        $this->actingAs($traveller)->get(route('dashboard'))->assertOk();
        $this->actingAs($traveller)->get(route('profile.edit'))->assertOk();
    }

    public function test_the_console_never_links_into_the_traveller_app(): void
    {
        [$trip, $leader] = $this->tripWithACost();
        $admin = User::factory()->admin()->create();

        foreach ([
            route('admin.dashboard'),
            route('admin.trips.index'),
            route('admin.users.show', $leader),
            route('admin.profile'),
        ] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertDontSee(route('dashboard'), false)
                ->assertDontSee(route('trips.show', $trip), false);
        }

        $this->actingAs($admin)->get(route('admin.trips.index'))->assertSee(route('admin.trips.show', $trip), false);
    }

    public function test_admins_read_a_trip_ledger_in_the_console_without_any_traveller_actions(): void
    {
        [$trip] = $this->tripWithACost();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.trips.show', $trip))
            ->assertOk()
            ->assertSee('Sajek cloud trip')
            ->assertSee('Hill cottage')
            ->assertSee('Nusrat Jahan')
            ->assertSee('Led by Rifat Leader')
            ->assertSee(route('admin.trips.end', $trip), false)
            // Every traveller trip action lives under /trips/{id}.
            ->assertDontSee(route('trips.show', $trip), false)
            ->assertDontSee('Your due')
            ->assertDontSee('Pay with bKash');
    }

    public function test_deleting_a_trip_from_its_console_page_returns_to_the_list(): void
    {
        [$trip] = $this->tripWithACost();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.trips.show', $trip))
            ->delete(route('admin.trips.destroy', $trip))
            ->assertRedirect(route('admin.trips.index'));

        $this->assertModelMissing($trip);
    }

    public function test_an_admin_manages_their_own_account_from_the_console(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Console Admin', 'email' => 'admin@example.com']);

        $this->actingAs($admin)->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Console Admin')
            ->assertSee(route('profile.update'), false)
            ->assertSee(route('profile.password'), false)
            ->assertSee(route('profile.avatar.update'), false);

        $this->actingAs($admin)
            ->from(route('admin.profile'))
            ->put(route('profile.update'), ['name' => 'Renamed Admin', 'username' => $admin->username, 'email' => 'admin@example.com'])
            ->assertRedirect(route('admin.profile'));

        $this->assertSame('Renamed Admin', $admin->refresh()->name);
    }

    public function test_invite_links_never_put_an_admin_on_a_trip(): void
    {
        [$trip] = $this->tripWithACost();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('trips.join.show', $trip->invite_code))
            ->assertRedirect(route('admin.trips.show', $trip));

        $this->actingAs($admin)->post(route('trips.join', $trip->invite_code))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($trip->members()->where('user_id', $admin->id)->exists());
    }

    public function test_signing_in_as_admin_leaves_pending_trip_invites_alone(): void
    {
        [$trip, $leader] = $this->tripWithACost();

        // Invited by email before the admin account had that address.
        app(TripInvitationService::class)->invite($trip, $leader, ['name' => 'Ops', 'email' => 'ops@example.com']);
        $admin = User::factory()->admin()->create(['email' => 'ops@example.com']);

        // Also arriving from an invite link.
        $this->get(route('trips.join.show', $trip->invite_code));

        $this->post(route('login.store'), ['login' => 'ops@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($trip->members()->where('user_id', $admin->id)->exists());
        $this->assertTrue($trip->members()->whereNull('user_id')->where('email', 'ops@example.com')->exists());
    }
}
