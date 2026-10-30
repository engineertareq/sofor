<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_travellers_cannot_open_the_admin_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.trips.index'))->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_administrator_can_see_the_console(): void
    {
        $admin = User::factory()->admin()->create();
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Platform overview');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.trips.index'))->assertOk()->assertSee($trip->title);
        $this->actingAs($admin)->get(route('admin.users.show', $leader))->assertOk();
    }

    public function test_the_console_shows_whether_each_email_and_phone_is_verified(): void
    {
        $admin = User::factory()->admin()->create();
        $traveller = User::factory()->create(['email' => 'nusrat@example.com', 'phone' => '+8801711000003']);
        $traveller->forceFill(['email_verified_at' => now(), 'phone_verified_at' => null])->save();
        $emailOnly = User::factory()->create(['email' => 'rafi@example.com', 'phone' => null]);

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'nusrat']))
            ->assertSeeInOrder(['nusrat@example.com', 'Verified', '+880 1711-000003', 'Not verified']);

        $this->actingAs($admin)->get(route('admin.users.show', $traveller))
            ->assertSeeInOrder(['Email', 'nusrat@example.com', 'Verified', 'Phone', '+880 1711-000003', 'Not verified', 'Joined']);

        // No number, nothing to verify.
        $this->actingAs($admin)->get(route('admin.users.show', $emailOnly))
            ->assertSeeInOrder(['rafi@example.com', 'Verified', 'Phone', '-', 'Joined'])
            ->assertDontSee('Not verified');
    }

    public function test_an_administrator_can_pause_an_account_but_not_their_own(): void
    {
        $admin = User::factory()->admin()->create();
        $traveler = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.toggle', $traveler))->assertRedirect();
        $this->assertFalse($traveler->refresh()->is_active);

        $this->actingAs($admin)->post(route('admin.users.toggle', $admin))->assertRedirect();
        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_an_administrator_can_end_a_trip_on_behalf_of_the_leader(): void
    {
        $admin = User::factory()->admin()->create();
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        $this->actingAs($admin)->post(route('admin.trips.end', $trip))->assertRedirect();

        $this->assertTrue($trip->refresh()->isEnded());
    }

    public function test_the_public_pages_render_without_any_demo_content(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Sofor');
        $this->get(route('login'))->assertOk()->assertDontSee('Demo accounts');
        $this->get(route('register'))->assertOk()->assertSee('Create your Sofor account');
        $this->get(route('terms'))->assertOk();
        $this->get(route('privacy'))->assertOk();
        $this->get(route('about'))->assertOk();
    }
}
