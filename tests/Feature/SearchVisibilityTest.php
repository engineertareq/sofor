<?php

namespace Tests\Feature;

use App\Http\Middleware\CanonicalHost;
use App\Models\User;
use App\Models\VisitedPlace;
use App\Services\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * How search engines find Sofor in both languages, reach one address per page,
 * and read the free tools: hreflang, ?lang=, the canonical host, and the
 * keyword pages (map maker, split calculator).
 */
class SearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // As on the live site: Bangla at the plain address, English at ?lang=en.
        config(['app.interface_locale' => 'bn']);
    }

    public function test_each_language_has_its_own_address_and_names_the_other(): void
    {
        $plain = route('map-maker');
        $english = $plain.'?lang=en';

        $bangla = $this->get($plain)->assertOk();
        $bangla->assertSee('<html lang="bn">', false)
            ->assertSee('<link rel="canonical" href="'.$plain.'">', false)
            ->assertSee('<link rel="alternate" hreflang="bn" href="'.$plain.'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$english.'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$plain.'">', false);

        // A crawler with no cookies gets English at ?lang=en, with its own canonical.
        $this->flushSession();
        $this->get($english)->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<link rel="canonical" href="'.e($english).'">', false)
            ->assertSee('Bangladesh travel map maker');

        // Other query parameters keep their place: the world board in English.
        $this->get(route('explorers', ['map' => 'world', 'lang' => 'en']))
            ->assertSee('<link rel="canonical" href="'.e(route('explorers', ['map' => 'world']).'&lang=en').'">', false);
    }

    public function test_switching_language_drops_the_lang_parameter_so_the_choice_sticks(): void
    {
        $this->from(route('about').'?lang=en')
            ->post(route('locale'), ['locale' => 'bn'])
            ->assertRedirect(route('about'));

        $this->get(route('about'))->assertSee('<html lang="bn">', false);
    }

    public function test_the_sitemap_lists_both_languages_with_hreflang(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $sitemap = simplexml_load_string($xml);
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locs = array_map('strval', $sitemap->xpath('//s:url/s:loc'));

        foreach ([route('map-maker'), route('map-maker', 'world'), route('split-calculator'), route('explorers')] as $url) {
            $this->assertContains($url, $locs);
            $this->assertContains($url.'?lang=en', $locs);
        }

        // The home page's English address keeps its slash: https://sofor.io/?lang=en.
        $this->assertContains(route('home'), $locs);
        $this->assertContains(route('home').'/?lang=en', $locs);

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.route('split-calculator').'?lang=en"/>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="x-default" href="'.route('split-calculator').'"/>', $xml);
    }

    public function test_the_live_site_answers_at_one_address_only(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://sofor.io']);

        $this->get('https://www.sofor.io/about?x=1')->assertStatus(301)->assertRedirect('https://sofor.io/about?x=1');
        $this->get('https://www.sofor.io/')->assertStatus(301)->assertRedirect('https://sofor.io');
        $this->get('https://sofor.io/about')->assertOk();

        // The test client trims trailing slashes, so these two go straight to the middleware.
        $redirect = fn (Request $request) => (new CanonicalHost)->handle($request, fn () => response('page'));

        // A trailing slash, and the folder the host rewrites into, are never addresses of their own.
        $response = $redirect(Request::create('https://sofor.io/travel-map/?map=world'));
        $this->assertSame([301, 'https://sofor.io/travel-map?map=world'], [$response->getStatusCode(), $response->headers->get('Location')]);

        $response = $redirect(Request::create('https://sofor.io/public/about', server: ['SCRIPT_NAME' => '/public/index.php', 'SCRIPT_FILENAME' => public_path('index.php')]));
        $this->assertSame([301, 'https://sofor.io/about'], [$response->getStatusCode(), $response->headers->get('Location')]);

        $this->assertSame('page', $redirect(Request::create('https://sofor.io/about'))->getContent());

        // Anywhere but production, nothing moves.
        $this->app['env'] = 'local';
        $this->assertSame('page', $redirect(Request::create('https://www.sofor.io/about/'))->getContent());
    }

    public function test_robots_and_the_sitemap_stay_readable_during_maintenance(): void
    {
        app(MaintenanceMode::class)->enable(User::factory()->admin()->create(), null);

        $this->get(route('home'))->assertStatus(503);
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_the_split_calculator_is_a_free_tool_page(): void
    {
        $html = $this->get(route('split-calculator').'?lang=en')->assertOk()
            ->assertSee('<title>Trip split calculator: who owes who - Sofor</title>', false)
            ->assertSee('<h1', false)
            ->assertSee('splitCalculator(', false)
            ->assertSee('How is the per-person cost worked out?')
            ->getContent();

        $types = $this->schemaTypes($html);
        $this->assertContains('WebApplication', $types);
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);

        // Linked from the header and the footer of every public page.
        $this->assertGreaterThanOrEqual(2, substr_count($this->get(route('about'))->getContent(), 'href="'.route('split-calculator').'"'));
    }

    public function test_each_map_maker_targets_its_own_search_and_lists_every_place(): void
    {
        $bangladesh = $this->get(route('map-maker').'?lang=en')->assertOk()
            ->assertSee('<title>Bangladesh travel map maker: how many districts have you visited? - Sofor</title>', false)
            ->assertSee('How do I make my travel map?')
            ->assertSee('All 64 districts of Bangladesh, by division')
            ->getContent();

        $this->assertMatchesRegularExpression('/Dhaka\s*<span[^>]*>13<\/span>\s*<\/h3>\s*<p[^>]*>[^<]*Gazipur/', $bangladesh);
        $this->assertContains('FAQPage', $this->schemaTypes($bangladesh));

        $this->get(route('map-maker', 'world').'?lang=en')->assertOk()
            ->assertSee('<title>Visited countries map maker: how many countries have you been to? - Sofor</title>', false)
            ->assertSee('All 195 countries of the world, by continent');

        // In Bangla, the words people type into Google. (A new visitor: ?lang=en was remembered.)
        $this->flushSession();
        $this->get(route('map-maker'))->assertSee('ভ্রমণ ম্যাপ বানান: বাংলাদেশের কয়টি জেলা ঘুরেছেন?')->assertSee('ভ্রমণ ম্যাপ কিভাবে বানাবো?');
    }

    public function test_later_pages_and_searches_of_the_board_stay_out_of_results(): void
    {
        $this->get(route('explorers', ['lang' => 'en']))->assertSee('index, follow', false);
        $this->get(route('explorers', ['page' => 2, 'lang' => 'en']))
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('page 2 - Sofor</title>', false)
            ->assertDontSee('rel="canonical"', false);
        $this->get(route('explorers', ['q' => 'nadia', 'lang' => 'en']))->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_meta_text_reads_in_bangla_with_bangla_digits(): void
    {
        $traveller = User::factory()->create(['username' => 'rifat', 'name' => 'Rifat Hasan']);
        VisitedPlace::query()->create(['user_id' => $traveller->id, 'map' => 'bd', 'area' => 'BD-13']);

        $html = $this->get($traveller->publicUrl())->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]+)"/', $html, $description);

        $this->assertStringNotContainsString(' of ', $description[1]);
        $this->assertStringContainsString('৬৪', $description[1]);
        $this->assertDoesNotMatchRegularExpression('/[0-9]/', $description[1]);
    }

    /** @return list<string> */
    private function schemaTypes(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);
        $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        return array_column($data['@graph'] ?? [$data], '@type');
    }
}
