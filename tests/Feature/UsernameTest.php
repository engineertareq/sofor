<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Username;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every account has a username: picked on sign up, changeable in settings,
 * and the address of the traveller's public profile.
 */
class UsernameTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = [])
    {
        return $this->from(route('register'))->post(route('register.store'), array_merge([
            'name' => 'Rifat Hasan',
            'username' => 'rifat_hasan',
            'contact_type' => 'email',
            'email' => 'rifat@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ], $overrides));
    }

    public function test_signing_up_needs_a_username_and_stores_it_lowercase(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('name="username"', false);

        $this->register(['username' => ''])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->register(['username' => '  @Rifat_Hasan '])->assertSessionHasNoErrors();

        $this->assertSame('rifat_hasan', User::where('email', 'rifat@example.com')->value('username'));
    }

    public function test_usernames_must_be_valid_free_and_not_reserved(): void
    {
        User::factory()->create(['username' => 'nusrat']);

        foreach (['ab', 'has space', 'dot.ted', '12345', str_repeat('a', 31), 'admin', 'sofor', 'Nusrat'] as $username) {
            $this->register(['username' => $username])->assertSessionHasErrors('username');
        }

        $this->assertSame(1, User::count());
    }

    public function test_the_live_check_explains_every_answer(): void
    {
        User::factory()->create(['username' => 'nusrat']);

        $check = fn (string $username) => $this->getJson(route('username.check', ['username' => $username]))->assertOk();

        $check('Rifat')->assertJson(['username' => 'rifat', 'available' => true]);
        $check('nusrat')->assertJson(['available' => false, 'message' => 'That username is taken. Try another one.']);
        $check('admin')->assertJson(['available' => false, 'message' => 'That username is reserved. Try another one.']);
        $check('a b')->assertJson(['available' => false]);

        // Signed in, your own username is fine.
        $this->actingAs(User::where('username', 'nusrat')->first());
        $check('nusrat')->assertJson(['available' => true, 'message' => 'That is your username now.']);
    }

    public function test_a_traveller_can_change_their_username_and_hide_their_profile(): void
    {
        $user = User::factory()->create(['username' => 'old_name', 'email' => 'rifat@example.com']);
        User::factory()->create(['username' => 'taken']);

        $save = fn (array $data) => $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), array_merge([
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'rifat@example.com',
        ], $data));

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('value="old_name"', false)
            ->assertSee('Public profile');

        $save(['username' => 'Taken'])->assertSessionHasErrors('username');
        $save(['username' => 'new_name'])->assertSessionHasNoErrors();

        $this->assertSame('new_name', $user->refresh()->username);
        $this->get('/@old_name')->assertNotFound();
        $this->get('/@new_name')->assertOk();

        $save(['username' => 'new_name', 'profile_public' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->profile_public);

        // Leaving the switch out (the admin form) changes nothing.
        $save(['username' => 'new_name'])->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->profile_public);
    }

    public function test_older_accounts_get_a_valid_free_username(): void
    {
        User::factory()->create(['username' => 'rifathasan']);

        $suggested = Username::suggest('Rifat Hasan', 'rifat@example.com');
        $this->assertNotSame('rifathasan', $suggested);
        $this->assertStringStartsWith('rifathasan', $suggested);
        $this->assertTrue(Username::isValid($suggested));

        // A Bangla only name falls back to the email, then to "traveller".
        $this->assertSame('nusrat', Username::suggest('নুসরাত', 'nusrat@example.com'));
        $this->assertTrue(Username::isValid(Username::suggest('নুসরাত', null)));
        // "traveller" itself is reserved, so the last resort gets digits.
        $fallback = Username::suggest('', null);
        $this->assertMatchesRegularExpression('/^traveller\d+$/', $fallback);
        $this->assertTrue(Username::isValid($fallback));
    }
}
