<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Services\BrandingService;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin -> Settings -> Branding: the uploaded logo and favicon replace the
 * built in artwork on every page, in the web app manifest and in trip PDFs.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(BrandingService::DISK);
    }

    private function branding(): BrandingService
    {
        // A fresh copy, the way the next request would see it.
        app()->forgetInstance(BrandingService::class);

        return app(BrandingService::class);
    }

    /** A wide wordmark with a transparent margin around it, like a typical export. */
    private function wideLogo(): UploadedFile
    {
        $image = imagecreatetruecolor(1894, 352);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 50, 50, 1843, 301, imagecolorallocatealpha($image, 16, 112, 106, 0));

        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('sofor-logo.png', (string) ob_get_clean());
    }

    private function storedImage(string $url): \GdImage
    {
        $path = BrandingService::DIRECTORY.'/'.basename(parse_url($url, PHP_URL_PATH));

        return imagecreatefromstring(Storage::disk(BrandingService::DISK)->get($path));
    }

    public function test_only_admins_reach_the_branding_settings(): void
    {
        $this->get(route('admin.settings'))->assertRedirect(route('login'));

        $traveller = User::factory()->create();
        $this->actingAs($traveller)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($traveller)->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()])->assertForbidden();

        $this->assertFalse($this->branding()->hasLogo());

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Upload a logo')
            ->assertSee('Upload a favicon');
    }

    public function test_an_uploaded_logo_is_trimmed_and_scaled_to_a_light_png(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.settings'))
            ->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasNoErrors();

        $logo = $this->storedImage($this->branding()->logoUrl());

        // 352px tall scaled to 240, then the 50px (now 34px) transparent margin trimmed away.
        $this->assertLessThanOrEqual(BrandingService::LOGO_HEIGHT, imagesy($logo));
        $this->assertEqualsWithDelta(1794 / 252, imagesx($logo) / imagesy($logo), 0.15);

        // The very edge is now the wordmark itself, not empty space.
        $edge = imagecolorsforindex($logo, imagecolorat($logo, 2, intdiv(imagesy($logo), 2)));
        $this->assertLessThan(30, $edge['alpha']);
    }

    public function test_the_logo_shows_on_every_kind_of_page_and_in_the_pdf(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()]);
        auth()->logout();

        $url = $this->branding()->logoUrl();

        // Public site, sign in screens and the console.
        $this->get(route('home'))->assertOk()->assertSee($url, false);
        $this->get(route('login'))->assertOk()->assertSee($url, false);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertSee($url, false);

        // The traveller app, and the trip PDF it downloads.
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        $this->actingAs($leader)->get(route('trips.index'))->assertOk()->assertSee($url, false);

        $pdf = $this->actingAs($leader)->get(route('trips.summary', $trip))->assertOk()->getContent();
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'The PDF masthead should embed the uploaded logo.');
    }

    public function test_emails_carry_the_logo_sized_for_mail_apps(): void
    {
        $traveller = User::factory()->create();
        $email = fn () => (string) (new VerifyEmailAddress('123456'))->toMail($traveller)->render();

        // Before anything is uploaded: Sofor's own logo as a PNG (mail apps do not show SVG).
        $this->assertStringContainsString(asset(BrandingService::EMAIL_FALLBACK), $email());
        $this->assertStringContainsString('width="82" height="40"', $email());

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()]);
        $this->branding();

        // The uploaded one, held to the width so a wide wordmark never takes over the email.
        $html = $email();
        $this->assertStringContainsString($this->branding()->logoUrl(), $html);
        $this->assertMatchesRegularExpression('/width="240" height="3\d"/', $html);
        $this->assertStringNotContainsString(BrandingService::EMAIL_FALLBACK, $html);
    }

    public function test_replacing_or_resetting_the_logo_cleans_up_after_itself(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()]);
        $first = $this->branding()->logoUrl();

        $this->actingAs($admin)->post(route('admin.settings.logo.update'), ['logo' => $this->wideLogo()]);
        $second = $this->branding()->logoUrl();

        $this->assertNotSame($first, $second);
        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();

        $this->actingAs($admin)->delete(route('admin.settings.logo.destroy'))->assertRedirect();

        $this->assertNull($this->branding()->logoUrl());
        $this->assertSame([], Storage::disk(BrandingService::DISK)->allFiles());
        $this->get(route('home'))->assertOk()->assertDontSee('/branding/', false);
    }

    public function test_a_favicon_upload_makes_every_icon_size(): void
    {
        // Not square, opaque red: padded to fit, never cropped.
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.favicon.update'), ['favicon' => UploadedFile::fake()->image('mark.png', 300, 150)])
            ->assertSessionHasNoErrors();

        $branding = $this->branding();

        foreach (BrandingService::FAVICON_SIZES as $size) {
            $icon = $this->storedImage($branding->faviconUrl($size));
            $this->assertSame([$size, $size], [imagesx($icon), imagesy($icon)]);
        }

        // The padding is transparent, except on the iOS icon which cannot show it.
        $corner = fn (int $size) => imagecolorsforindex($icon = $this->storedImage($branding->faviconUrl($size)), imagecolorat($icon, 1, 1));
        $this->assertSame(127, $corner(512)['alpha']);
        $this->assertSame(['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 0], $corner(180));
    }

    public function test_the_favicon_reaches_the_page_head_and_the_app_manifest(): void
    {
        $this->getJson(route('manifest'))->assertOk()->assertJsonPath('icons.0.src', asset('icons/icon-192.png'));
        $this->get(route('home'))->assertSee(asset('favicon.svg'), false);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.settings.favicon.update'), ['favicon' => UploadedFile::fake()->image('mark.png', 512, 512)]);
        auth()->logout();

        $branding = $this->branding();

        $this->get(route('home'))
            ->assertSee('<link rel="icon" href="'.$branding->faviconUrl(32).'"', false)
            ->assertSee('<link rel="apple-touch-icon" href="'.$branding->faviconUrl(180).'"', false)
            ->assertSee(route('manifest'), false)
            ->assertDontSee(asset('favicon.svg'), false);

        $this->getJson(route('manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('icons.0.src', $branding->faviconUrl(192))
            ->assertJsonPath('icons.1.src', $branding->faviconUrl(512));

        $response = $this->get($branding->faviconUrl(32))->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    public function test_unusable_images_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $post = fn (string $route, string $field, UploadedFile $file) => $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route($route), [$field => $file]);

        $post('admin.settings.logo.update', 'logo', UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf'))->assertSessionHasErrors('logo');
        $post('admin.settings.logo.update', 'logo', UploadedFile::fake()->image('thin.png', 400, 20))->assertSessionHasErrors('logo');
        $post('admin.settings.logo.update', 'logo', UploadedFile::fake()->createWithContent('logo.png', 'not an image'))->assertSessionHasErrors('logo');
        $post('admin.settings.favicon.update', 'favicon', UploadedFile::fake()->image('tiny.png', 32, 32))->assertSessionHasErrors('favicon');
        $post('admin.settings.favicon.update', 'favicon', UploadedFile::fake()->image('big.png', 512, 512)->size(BrandingService::FAVICON_MAX_KILOBYTES + 1))
            ->assertSessionHasErrors('favicon');

        $this->assertSame([], Storage::disk(BrandingService::DISK)->allFiles());
        $this->get(route('branding.asset', 'logo-'.str_repeat('a', 24).'.png'))->assertNotFound();
    }

    public function test_the_site_keeps_its_built_in_branding_before_the_settings_table_exists(): void
    {
        Schema::drop('settings');

        $this->get(route('home'))->assertOk()->assertSee(asset('favicon.svg'), false);
        $this->get(route('login'))->assertOk();
    }
}
