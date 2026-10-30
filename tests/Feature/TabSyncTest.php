<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every page says who it was made for, in which language and when, so the
 * other open tabs follow a sign in, a sign out or a new language
 * (resources/js/tab-sync.js).
 */
class TabSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: string, locale: string, at: int}|null */
    private function sessionTag(TestResponse $page): ?array
    {
        if (! preg_match('/<meta name="sofor-session" data-user="([^"]*)" data-locale="([^"]*)"\s+data-at="(\d+)">/', $page->getContent(), $match)) {
            return null;
        }

        return ['user' => $match[1], 'locale' => $match[2], 'at' => (int) $match[3]];
    }

    public function test_a_page_names_who_it_was_made_for_and_when(): void
    {
        config(['app.interface_locale' => 'bn']);   // the real default; the suite itself reads in English

        $this->assertSame(['user' => '', 'locale' => 'bn'], array_slice($this->sessionTag($this->get(route('home'))), 0, 2));

        $user = User::factory()->create(['locale' => 'en']);
        $session = $this->sessionTag($this->actingAs($user)->get(route('dashboard'))->assertOk());

        $this->assertSame((string) $user->id, $session['user']);
        $this->assertSame('en', $session['locale']);
        $this->assertEqualsWithDelta(now()->getTimestampMs(), $session['at'], 5000);
    }

    public function test_the_tabs_follow_the_language_the_visit_settled_on_not_the_address(): void
    {
        // Signed in, the account's choice stands; ?lang= only changes the one page.
        $user = User::factory()->create(['locale' => 'bn']);
        $page = $this->actingAs($user)->get(route('dashboard', ['lang' => 'en']))->assertSee('<html lang="en">', false);
        $this->assertSame('bn', $this->sessionTag($page)['locale']);

        auth()->logout();

        // A visitor's ?lang= is remembered for the visit, so the other tabs follow it.
        $page = $this->get(route('home', ['lang' => 'en']))->assertSee('<html lang="en">', false);
        $this->assertSame('en', $this->sessionTag($page)['locale']);
    }

    public function test_the_404_still_knows_who_is_signed_in(): void
    {
        // Otherwise a mistyped address would look like a sign out to every other tab.
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get('/no-such-page')->assertNotFound();

        $this->assertSame((string) $user->id, $this->sessionTag($page)['user']);
    }

    public function test_only_pages_of_the_app_reload_after_a_change_in_another_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-reload-on-change', false);
        $this->get(route('home'))->assertDontSee('data-reload-on-change', false);
    }
}
