<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What search engines and link previews see: titles, descriptions, canonical
 * addresses, structured data, robots.txt and the sitemap.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> meta name/property => content */
    private function meta(string $html): array
    {
        preg_match_all('/<meta (?:name|property)="([^"]+)" content="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => html_entity_decode($m[2], ENT_QUOTES)])->all();
    }

    public function test_the_home_page_is_ready_for_search_and_sharing(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $meta = $this->meta($html);

        $this->assertStringContainsString('<link rel="canonical" href="'.route('home').'">', $html);
        $this->assertStringStartsWith('index, follow', $meta['robots']);
        $this->assertSame('summary_large_image', $meta['twitter:card']);
        $this->assertSame(asset('images/og/sofor.png'), $meta['og:image']);
        $this->assertSame(route('home'), $meta['og:url']);
        $this->assertLessThanOrEqual(160, mb_strlen($meta['description']));

        // Structured data parses, says the app is free, and carries the page's FAQ.
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);
        $graph = collect(json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');

        $this->assertSame('0', $graph['WebApplication']['offers']['price']);
        $this->assertSame('Sofor', $graph['Organization']['name']);
        $this->assertSame('Is Sofor actually free?', $graph['FAQPage']['mainEntity'][0]['name']);
        $this->assertCount(8, $graph['FAQPage']['mainEntity']);
    }

    public function test_public_pages_each_have_their_own_description(): void
    {
        $descriptions = collect(['home', 'about', 'terms', 'privacy', 'support.index', 'sponsors', 'explorers', 'login', 'register'])
            ->mapWithKeys(fn (string $route) => [$route => $this->meta($this->get(route($route))->assertOk()->getContent())['description']]);

        $this->assertSame($descriptions->count(), $descriptions->unique()->count(), 'Two pages share a meta description.');
    }

    public function test_private_and_token_pages_are_kept_out_of_search(): void
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        foreach ([route('trips.join.show', $trip->invite_code), route('password.request'), route('password.reset', 'token'), route('support.thanks')] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, $url);
            $this->assertStringNotContainsString('rel="canonical"', $html, $url);
        }

        $html = $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->getContent();
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_every_explorers_board_is_its_own_page_but_a_search_is_not(): void
    {
        $this->get(route('explorers'))->assertSee('<link rel="canonical" href="'.route('explorers').'">', false);
        $this->get(route('explorers', ['map' => 'world']))
            ->assertSee('<link rel="canonical" href="'.e(route('explorers', ['map' => 'world'])).'">', false)
            ->assertSee('<title>Explorers worldwide: who has visited the most countries? - Sofor</title>', false);
        $this->get(route('explorers', ['q' => 'nadia']))->assertSee('<meta name="robots" content="noindex, follow">', false)->assertDontSee('rel="canonical"', false);
    }

    public function test_robots_txt_keeps_crawlers_off_staging_and_out_of_private_pages(): void
    {
        // Not production: nothing is crawled.
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee("Disallow: /\n", false);

        $this->app['env'] = 'production';
        $robots = $this->get('/robots.txt')->getContent();

        foreach (['/dashboard', '/trips', '/admin', '/join/', '/reset-password', '/verify'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path."\n", $robots);
        }
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
        $this->assertStringNotContainsString("Disallow: /\n", $robots);
    }

    public function test_the_sitemap_lists_the_public_pages_only(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $urls = collect();
        foreach ($xml->url as $url) {
            $urls->push((string) $url->loc);
        }

        foreach (['home', 'explorers', 'sponsors', 'support.index', 'about', 'privacy', 'terms', 'register'] as $route) {
            $this->assertContains(route($route), $urls, $route);
        }
        $this->assertContains(route('explorers', ['map' => 'world']), $urls);
        $this->assertTrue($urls->every(fn (string $url) => ! str_contains($url, '/dashboard') && ! str_contains($url, '/trips') && ! str_contains($url, '/admin')));
    }

    public function test_the_icons_and_share_image_exist(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'icons/apple-touch-icon.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png', 'images/og/sofor.png'] as $file) {
            $this->assertGreaterThan(100, (int) @filesize(public_path($file)), $file);
        }

        [$width, $height] = getimagesize(public_path('images/og/sofor.png'));
        $this->assertSame([1200, 630], [$width, $height]);

        $this->getJson(route('manifest'))->assertJsonPath('theme_color', '#fbf8f2')->assertJsonPath('icons.2.purpose', 'maskable');
    }
}
