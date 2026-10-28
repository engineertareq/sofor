<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\SplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConfirmsEmail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use ConfirmsEmail, RefreshDatabase;

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rifat Hasan',
            'username' => 'rifat_hasan',
            'contact_type' => 'email',
            'email' => 'rifat@example.com',
            'phone' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ], $overrides);
    }

    public function test_the_first_account_on_a_fresh_install_becomes_the_administrator(): void
    {
        // The admin account lands in the console, never the traveller app.
        $this->post(route('register.store'), $this->registrationPayload())
            ->assertRedirect(route('admin.dashboard'));

        $first = User::first();
        $this->assertTrue($first->isAdmin());

        $this->post(route('logout'));

        $this->post(route('register.store'), $this->registrationPayload([
            'name' => 'Nusrat Jahan',
            'username' => 'nusrat',
            'email' => 'nusrat@example.com',
        ]))->assertRedirect(route('verification.notice'));

        $second = User::where('email', 'nusrat@example.com')->first();
        $this->assertFalse($second->isAdmin());
    }

    public function test_users_can_register_with_an_email_address(): void
    {
        $this->post(route('register.store'), $this->registrationPayload());

        $user = User::where('email', 'rifat@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->phone);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_with_a_phone_number_normalises_the_number(): void
    {
        // Somebody else registered first, so this is an ordinary traveller account.
        User::factory()->admin()->create();

        $this->post(route('register.store'), $this->registrationPayload([
            'contact_type' => 'phone',
            'email' => null,
            'phone' => '01712345678',
        ]))->assertRedirect(route('verification.notice'));

        $user = User::where('phone', '+8801712345678')->firstOrFail();

        $this->assertSame('+8801712345678', $user->phone);
        $this->assertNull($user->email);
    }

    public function test_invalid_bangladeshi_phone_numbers_are_rejected(): void
    {
        $this->from(route('register'))->post(route('register.store'), $this->registrationPayload([
            'contact_type' => 'phone',
            'email' => null,
            'phone' => '12345',
        ]))->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_a_user_can_log_in_with_a_phone_number_or_an_email(): void
    {
        $user = User::factory()->create([
            'email' => 'ayesha@example.com',
            'phone' => '+8801712345678',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login.store'), ['login' => '01712345678', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['login' => 'ayesha@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_paused_account_cannot_log_in(): void
    {
        User::factory()->paused()->create([
            'email' => 'paused@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login.store'), ['login' => 'paused@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_pending_invites_are_claimed_once_the_invited_person_confirms_their_email(): void
    {
        Notification::fake();
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        app(\App\Services\TripInvitationService::class)->invite($trip, $leader, [
            'name' => 'New Traveller',
            'email' => 'invited@example.com',
        ]);

        $pending = TripMember::where('email', 'invited@example.com')->first();
        $this->assertNull($pending->user_id);

        $this->post(route('register.store'), $this->registrationPayload(['email' => 'invited@example.com']));

        // Typing an address into the sign up form proves nothing yet.
        $this->assertNull(TripMember::where('email', 'invited@example.com')->value('user_id'));

        $this->confirmEmail('invited@example.com')->assertRedirect(route('dashboard'));

        $joined = TripMember::where('email', 'invited@example.com')->first();

        $this->assertSame(User::where('email', 'invited@example.com')->value('id'), $joined->user_id);
        $this->assertSame('active', $joined->status->value);
    }

    public function test_the_split_maths_never_loses_a_paisha(): void
    {
        $splitter = new SplitService;

        $threeWay = $splitter->distribute(100000, [1 => 1, 2 => 1, 3 => 1]);
        $this->assertSame(100000, array_sum($threeWay));
        $this->assertSame([1 => 33334, 2 => 33333, 3 => 33333], $threeWay);

        $weighted = $splitter->distribute(500000, [1 => 2, 2 => 1, 3 => 1, 4 => 1]);
        $this->assertSame(500000, array_sum($weighted));
        $this->assertSame(200000, $weighted[1]);
        $this->assertSame(100000, $weighted[2]);

        $awkward = $splitter->distribute(999999, [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1]);
        $this->assertSame(999999, array_sum($awkward));
    }
}
