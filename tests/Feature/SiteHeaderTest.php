<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public site's header (components/site-header): mega menus and the language switch. */
class SiteHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_language_switch_lives_in_the_header_not_the_footer(): void
    {
        $page = $this->get(route('home'))->assertOk()->getContent();
        $switch = 'action="'.route('locale').'"';

        $this->assertStringContainsString($switch, $this->between($page, '<header class="site-header', '</header>'));
        $this->assertStringNotContainsString($switch, $this->between($page, '<footer', '</footer>'));

        // The language already showing does not reload the page; the other one does.
        $this->assertMatchesRegularExpression('/type="button" name="locale" value="en"/', $page);
        $this->assertMatchesRegularExpression('/type="submit" name="locale" value="bn"/', $page);

        // The sign-in pages carry it at the top too.
        $this->get(route('login'))->assertOk()->assertSee($switch, false);
    }

    public function test_the_mega_menus_reach_every_public_page(): void
    {
        $header = $this->between($this->get(route('home'))->assertOk()->getContent(), '<header class="site-header', '</header>');

        foreach (['product', 'explore', 'community'] as $menu) {
            $this->assertStringContainsString('data-mega-trigger="'.$menu.'"', $header);
        }

        foreach ([route('home').'#how', route('home').'#faq', route('explorers'), route('map-maker'), route('about'), route('sponsors'), route('privacy'), route('support.index'), route('register')] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $header);
        }

        $this->assertStringNotContainsString('href="'.route('travel.index').'"', $header);
    }

    public function test_a_traveller_finds_their_own_map_and_profile_under_explore(): void
    {
        $traveller = User::factory()->create(['username' => 'rifat']);

        $header = $this->between($this->actingAs($traveller)->get(route('about'))->assertOk()->getContent(), '<header class="site-header', '</header>');

        $this->assertStringContainsString('href="'.route('travel.index').'"', $header);
        $this->assertStringContainsString('href="'.$traveller->publicUrl().'"', $header);
        $this->assertStringContainsString('href="'.route('dashboard').'"', $header);
        $this->assertStringNotContainsString('href="'.route('register').'"', $header);

        // The section you are in is marked.
        $this->assertMatchesRegularExpression('/data-mega-trigger="community"[^>]*data-current/', $header);
        $this->assertDoesNotMatchRegularExpression('/data-mega-trigger="explore"[^>]*data-current/', $header);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "Missing {$from}");

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }
}
