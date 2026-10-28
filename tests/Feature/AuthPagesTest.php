<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sign-in and sign-up pages (layouts/auth): the form and nothing else, each a tap from the other. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_in_and_sign_up_link_to_each_other(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('aria-label="Log in or sign up"', false)
            ->assertSee('New to Sofor?')
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('href="'.route('password.request').'"', false);

        $this->get(route('register'))->assertOk()
            ->assertSee('Already have an account?')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('8+ characters');
    }

    public function test_only_the_form_shows_and_the_map_behind_it_is_decoration(): void
    {
        foreach ([route('login'), route('register'), route('password.request')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<div aria-hidden="true" class="auth-art pointer-events-none select-none', false)
                ->assertDontSee('Money sorted, so the')        // the old marketing column
                ->assertDontSee('Account help');
        }
    }
}
