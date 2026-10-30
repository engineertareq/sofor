<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The alerts panel: its rows come a page at a time from notifications.feed. */
class NotificationsPanelTest extends TestCase
{
    use RefreshDatabase;

    /** Alerts numbered from the oldest, a minute apart; the first $read of them already read. */
    private function alerts(User $user, int $count, int $read = 0): void
    {
        foreach (range(1, $count) as $n) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test',
                'data' => ['title' => "Alert number {$n}.", 'body' => 'Rafi added a cost.', 'url' => route('dashboard'), 'icon' => 'receipt', 'tone' => 'info'],
                'read_at' => $n <= $read ? now() : null,
                'created_at' => now()->subMinutes($count - $n),
            ]);
        }
    }

    /** @return array<int, string> the alert titles in a feed answer, in order */
    private function titles(array $page): array
    {
        preg_match_all('/Alert number (\d+)\./', $page['html'], $matches);

        return $matches[1];
    }

    public function test_the_feed_pages_through_every_alert_newest_first_without_repeats(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 20, read: 4);

        $first = $this->actingAs($user)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->json();

        $this->assertSame(array_map('strval', range(20, 6)), $this->titles($first));
        $this->assertSame(16, $first['unread']);
        $this->assertNotNull($first['next']);

        $second = $this->getJson($first['next'])->assertOk()->json();

        $this->assertSame(['5', '4', '3', '2', '1'], $this->titles($second));
        $this->assertNull($second['next']);
    }

    public function test_a_row_is_a_form_that_marks_its_alert_read(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 2, read: 1);

        $html = $this->actingAs($user)->getJson(route('notifications.feed'))->json('html');

        $this->assertSame(2, substr_count($html, 'data-alert'));
        $this->assertSame(1, substr_count($html, 'data-unread'), 'Only the unread alert looks unread.');
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString(route('notifications.read', $user->notifications()->latest()->value('id')), $html);
    }

    public function test_the_unread_tab_shows_only_unread_alerts(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 5, read: 3);

        $page = $this->actingAs($user)->getJson(route('notifications.feed', ['unread' => 1]))->json();

        $this->assertSame(['5', '4'], $this->titles($page));
        $this->assertSame(2, $page['unread']);
    }

    public function test_the_feed_is_only_ever_your_own(): void
    {
        $user = User::factory()->create();
        $this->alerts(User::factory()->create(), 3);

        $page = $this->actingAs($user)->getJson(route('notifications.feed'))->json();
        $this->assertSame([], $this->titles($page));
        $this->assertSame(0, $page['unread']);

        auth()->logout();
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    public function test_opening_an_alert_says_where_to_go_and_how_many_are_left(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 3);
        $id = $user->notifications()->latest()->value('id');

        $this->actingAs($user)->postJson(route('notifications.read', $id))
            ->assertOk()
            ->assertExactJson(['redirect' => route('dashboard'), 'unread' => 2]);

        $this->postJson(route('notifications.read-all'))->assertExactJson(['unread' => 0]);
    }

    public function test_bangla_rows_get_bangla_digits(): void
    {
        $user = User::factory()->create(['locale' => 'bn']);
        $this->alerts($user, 1);

        $html = $this->actingAs($user)->getJson(route('notifications.feed'))->json('html');

        $this->assertStringContainsString('Alert number ১.', $html);
    }

    public function test_the_bell_and_the_alerts_tab_open_the_panel_and_still_lead_to_the_page(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 3);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $page->assertSee('id="app-alerts"', false)
            ->assertSee(str_replace('/', '\/', route('notifications.feed')), false)
            ->assertSee("\$store.sheets.toggle('alerts', \$el)", false)
            ->assertSee('href="'.route('notifications.index').'"', false);

        // The bell, the tab bar and the sidebar each show the count, and update it in place.
        $this->assertSame(3, substr_count($page->getContent(), 'data-alerts-badge'));
    }

    public function test_the_alerts_page_uses_the_same_rows(): void
    {
        $user = User::factory()->create();
        $this->alerts($user, 2, read: 1);

        $html = $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-alert '));
        $this->assertStringContainsString('Alert number 2.', $html);
    }
}
