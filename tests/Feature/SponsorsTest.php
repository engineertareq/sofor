<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Models\User;
use App\Services\SponsorLogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sponsors: managed in Admin -> Sponsors, shown in the home page carousel and
 * on /sponsors, where brands can also ask to become one.
 */
class SponsorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SponsorLogos::disk());
    }

    /** A wide wordmark with a transparent margin, like a typical logo export. */
    private function logo(string $name = 'logo.png', int $width = 1200, int $height = 400): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 100, 100, $width - 101, $height - 101, imagecolorallocatealpha($image, 15, 111, 106, 0));

        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    private function sponsor(array $attributes = []): Sponsor
    {
        $path = SponsorLogos::DIRECTORY.'/'.Str::random(24).'.png';
        Storage::disk(SponsorLogos::disk())->put($path, 'png');

        return Sponsor::create($attributes + [
            'name' => 'Sponsor '.Str::random(5),
            'website' => 'https://example.com',
            'tier' => 'silver',
            'logo_path' => $path,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function inquiry(array $attributes = []): SponsorInquiry
    {
        return SponsorInquiry::create($attributes + [
            'name' => 'Nadia Rahman',
            'company' => 'Green Line Travels',
            'email' => 'nadia@greenline.test',
            'message' => 'We would love to sponsor the home page this winter.',
            'status' => SponsorInquiry::STATUS_NEW,
        ]);
    }

    /* ------------------------------------------------------------------
       Public
    ------------------------------------------------------------------ */

    public function test_the_sponsors_page_shows_live_sponsors_by_tier_and_hides_the_rest(): void
    {
        $this->sponsor(['name' => 'Skyline Air', 'tier' => 'platinum', 'tagline' => 'Fly the whole country']);
        $this->sponsor(['name' => 'Hill Track Gear', 'tier' => 'gold']);
        $this->sponsor(['name' => 'Cha Corner', 'tier' => 'silver']);
        $this->sponsor(['name' => 'Retired Brand', 'is_active' => false]);

        $this->get(route('sponsors'))
            ->assertOk()
            ->assertSeeInOrder(['Skyline Air', 'Fly the whole country', 'Hill Track Gear', 'Cha Corner'])
            ->assertDontSee('Retired Brand')
            ->assertSee('rel="sponsored noopener"', false)
            ->assertSee('Become a sponsor');
    }

    public function test_the_sponsors_page_invites_a_first_sponsor_when_there_are_none(): void
    {
        $this->get(route('sponsors'))
            ->assertOk()
            ->assertSee('This wall is waiting for its first logo.');
    }

    public function test_a_tier_in_the_link_is_picked_in_the_form(): void
    {
        $this->get(route('sponsors', ['tier' => 'gold']))
            ->assertOk()
            ->assertSee("tier: 'gold'", false);

        // Anything else is ignored rather than trusted.
        $this->get(route('sponsors', ['tier' => 'diamond']))
            ->assertOk()
            ->assertSee("tier: ''", false);
    }

    public function test_the_home_page_invites_a_first_sponsor_when_there_are_none(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sponsored by')
            ->assertSee('Your brand could be the first one here')
            ->assertDontSee('sponsor-marquee', false);
    }

    public function test_one_or_two_sponsors_sit_still_beside_an_open_spot(): void
    {
        $sponsor = $this->sponsor(['name' => 'Cha Corner']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($sponsor->logoUrl(), false)
            ->assertSee('Your brand here')
            ->assertDontSee('sponsor-marquee', false);
    }

    public function test_three_or_more_sponsors_scroll_past_on_the_home_page(): void
    {
        $platinum = $this->sponsor(['name' => 'Skyline Air', 'tier' => 'platinum']);
        $this->sponsor(['name' => 'Cha Corner']);
        $this->sponsor(['name' => 'Hill Track Gear', 'tier' => 'gold']);
        $hidden = $this->sponsor(['name' => 'Retired Brand', 'is_active' => false]);

        $response = $this->get(route('home'))->assertOk()->assertSee('sponsor-marquee', false);

        // Highest tier first, and hidden sponsors never reach the page.
        $response->assertSeeInOrder(['alt="Skyline Air"', 'alt="Hill Track Gear"', 'alt="Cha Corner"'], false)
            ->assertSee($platinum->logoUrl(), false)
            ->assertDontSee($hidden->logoUrl(), false);

        // The looping copy is hidden from screen readers.
        $this->assertMatchesRegularExpression('/class="sponsor-set"\s+aria-hidden="true"/', $response->getContent());
    }

    public function test_anyone_can_ask_to_become_a_sponsor(): void
    {
        $this->post(route('sponsors.inquiries.store'), [
            'name' => 'Nadia Rahman',
            'company' => 'Green Line Travels',
            'email' => 'nadia@greenline.test',
            'phone' => '+880 1712-345678',
            'website' => 'greenline.test',
            'tier' => 'gold',
            'message' => 'We would love to sponsor the home page this winter.',
        ])->assertRedirect(route('sponsors').'#contact')
            ->assertSessionHas('sponsor_inquiry', ['name' => 'Nadia Rahman', 'email' => 'nadia@greenline.test']);

        $inquiry = SponsorInquiry::firstOrFail();
        $this->assertSame('Green Line Travels', $inquiry->company);
        $this->assertSame('https://greenline.test', $inquiry->website);
        $this->assertSame('gold', $inquiry->tier);
        $this->assertSame(SponsorInquiry::STATUS_NEW, $inquiry->status);

        $this->withSession(['sponsor_inquiry' => ['name' => 'Nadia Rahman', 'email' => 'nadia@greenline.test']])
            ->get(route('sponsors'))
            ->assertSee('Thank you, Nadia Rahman!')
            ->assertSee('nadia@greenline.test');
    }

    public function test_a_sponsor_request_is_checked_and_sent_back_to_the_form(): void
    {
        $this->from(route('sponsors'))
            ->post(route('sponsors.inquiries.store'), ['email' => 'not-an-email', 'tier' => 'diamond', 'message' => 'Hi'])
            ->assertRedirect(route('sponsors').'#contact')
            ->assertSessionHasErrors(['name', 'company', 'email', 'tier', 'message']);

        $this->assertSame(0, SponsorInquiry::count());
    }

    public function test_not_sure_yet_is_a_valid_choice(): void
    {
        $this->post(route('sponsors.inquiries.store'), [
            'name' => 'Rakib',
            'company' => 'Cha Corner',
            'email' => 'rakib@cha.test',
            'tier' => '',
            'message' => 'Not sure which tier fits us yet.',
        ])->assertSessionHasNoErrors();

        $this->assertNull(SponsorInquiry::firstOrFail()->tier);
    }

    public function test_a_bot_filling_the_hidden_field_is_thanked_but_nothing_is_saved(): void
    {
        $this->post(route('sponsors.inquiries.store'), [
            'name' => 'Bot',
            'company' => 'Spam Inc',
            'email' => 'bot@spam.test',
            'message' => 'Buy cheap things from us today.',
            'nickname' => 'gotcha',
        ])->assertRedirect(route('sponsors').'#contact');

        $this->assertSame(0, SponsorInquiry::count());
    }

    public function test_hidden_logos_are_only_served_to_admins(): void
    {
        $live = $this->sponsor();
        $hidden = $this->sponsor(['is_active' => false]);

        $this->get($live->logoUrl())->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($hidden->logoUrl())->assertNotFound();
        $this->get(route('sponsors.logo', Str::random(24).'.png'))->assertNotFound();

        $this->actingAs($this->admin())->get($hidden->logoUrl())->assertOk();
    }

    /* ------------------------------------------------------------------
       Admin -> Sponsors
    ------------------------------------------------------------------ */

    public function test_only_admins_reach_the_sponsor_console(): void
    {
        $traveller = User::factory()->create();
        $sponsor = $this->sponsor();
        $inquiry = $this->inquiry();

        $this->get(route('admin.sponsors.index'))->assertRedirect(route('login'));

        $this->actingAs($traveller);
        $this->get(route('admin.sponsors.index'))->assertForbidden();
        $this->post(route('admin.sponsors.store'), ['name' => 'X', 'tier' => 'gold', 'logo' => $this->logo()])->assertForbidden();
        $this->delete(route('admin.sponsors.destroy', $sponsor))->assertForbidden();
        $this->get(route('admin.sponsor-inquiries.index'))->assertForbidden();
        $this->get(route('admin.sponsor-inquiries.show', $inquiry))->assertForbidden();

        $this->assertSame(1, Sponsor::count());
    }

    public function test_an_admin_adds_a_sponsor_with_its_logo(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.sponsors.index'))
            ->assertOk()
            ->assertSee('Add a sponsor');

        $this->post(route('admin.sponsors.store'), [
            'name' => 'Skyline Air',
            'website' => 'skyline.test',
            'tier' => 'platinum',
            'tagline' => 'Fly the whole country',
            'is_active' => '1',
            'logo' => $this->logo(),
        ])->assertRedirect(route('admin.sponsors.index'))->assertSessionHas('status');

        $sponsor = Sponsor::firstOrFail();
        $this->assertSame('https://skyline.test', $sponsor->website);
        $this->assertSame('platinum', $sponsor->tier);
        $this->assertTrue($sponsor->is_active);
        Storage::disk(SponsorLogos::disk())->assertExists($sponsor->logo_path);

        // Redrawn as a PNG, scaled down to the stored height, transparent margins trimmed.
        $stored = imagecreatefromstring(Storage::disk(SponsorLogos::disk())->get($sponsor->logo_path));
        // 1200x400 scales to 720x240; trimming the 60px margins leaves 600x120.
        $this->assertEqualsWithDelta(600, imagesx($stored), 2);
        $this->assertEqualsWithDelta(120, imagesy($stored), 2);

        $this->get(route('home'))->assertSee($sponsor->logoUrl(), false);
    }

    public function test_a_new_sponsor_needs_a_name_a_tier_and_a_logo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sponsors.store'), ['tier' => 'diamond', 'website' => 'not a site'])
            ->assertSessionHasErrors(['name', 'tier', 'logo', 'website']);

        $this->post(route('admin.sponsors.store'), [
            'name' => 'Skyline Air',
            'tier' => 'gold',
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, Sponsor::count());
    }

    public function test_an_admin_edits_a_sponsor_and_the_replaced_logo_is_deleted(): void
    {
        $sponsor = $this->sponsor(['name' => 'Old Name']);
        $oldLogo = $sponsor->logo_path;

        $this->actingAs($this->admin())
            ->get(route('admin.sponsors.edit', $sponsor))
            ->assertOk()
            ->assertSee('Old Name');

        $this->put(route('admin.sponsors.update', $sponsor), [
            'name' => 'New Name',
            'website' => '',
            'tier' => 'gold',
            'is_active' => '1',
            'logo' => $this->logo(),
        ])->assertRedirect(route('admin.sponsors.index'));

        $sponsor->refresh();
        $this->assertSame('New Name', $sponsor->name);
        $this->assertNull($sponsor->website);
        $this->assertSame('gold', $sponsor->tier);
        $this->assertNotSame($oldLogo, $sponsor->logo_path);
        Storage::disk(SponsorLogos::disk())->assertMissing($oldLogo);
        Storage::disk(SponsorLogos::disk())->assertExists($sponsor->logo_path);
    }

    public function test_editing_without_a_new_logo_keeps_the_old_one(): void
    {
        $sponsor = $this->sponsor();
        $logo = $sponsor->logo_path;

        $this->actingAs($this->admin())->put(route('admin.sponsors.update', $sponsor), [
            'name' => 'Renamed',
            'tier' => 'silver',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $sponsor->refresh();
        $this->assertSame($logo, $sponsor->logo_path);
        $this->assertFalse($sponsor->is_active);
        Storage::disk(SponsorLogos::disk())->assertExists($logo);
    }

    public function test_hiding_a_sponsor_takes_it_off_the_site_but_keeps_it_in_the_console(): void
    {
        $sponsor = $this->sponsor(['name' => 'Cha Corner']);

        $this->actingAs($this->admin())->post(route('admin.sponsors.toggle', $sponsor))->assertRedirect();
        $this->assertFalse($sponsor->refresh()->is_active);

        $this->get(route('admin.sponsors.index'))->assertSee('Cha Corner')->assertSee('Hidden');
        $this->get(route('sponsors'))->assertDontSee('Cha Corner');

        $this->post(route('admin.sponsors.toggle', $sponsor));
        $this->assertTrue($sponsor->refresh()->is_active);
    }

    public function test_sponsors_are_reordered_within_their_tier(): void
    {
        $first = $this->sponsor(['name' => 'First', 'sort_order' => 1]);
        $this->sponsor(['name' => 'Second', 'sort_order' => 2]);
        $third = $this->sponsor(['name' => 'Third', 'sort_order' => 3]);

        $this->actingAs($this->admin())->post(route('admin.sponsors.move', $third), ['direction' => 'up'])->assertRedirect();

        $this->assertSame(['First', 'Third', 'Second'], Sponsor::query()->showcase()->pluck('name')->all());

        // Already at the top: nothing changes.
        $this->post(route('admin.sponsors.move', $first), ['direction' => 'up']);
        $this->assertSame(['First', 'Third', 'Second'], Sponsor::query()->showcase()->pluck('name')->all());

        $this->post(route('admin.sponsors.move', $first), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_removing_a_sponsor_deletes_its_logo(): void
    {
        $sponsor = $this->sponsor();

        $this->actingAs($this->admin())->delete(route('admin.sponsors.destroy', $sponsor))->assertRedirect(route('admin.sponsors.index'));

        $this->assertSame(0, Sponsor::count());
        Storage::disk(SponsorLogos::disk())->assertMissing($sponsor->logo_path);
    }

    /* ------------------------------------------------------------------
       Admin -> Sponsor requests
    ------------------------------------------------------------------ */

    public function test_admins_see_new_requests_in_the_menu_and_on_the_dashboard(): void
    {
        $this->inquiry(['company' => 'Green Line Travels']);
        $this->inquiry(['company' => 'Old Request', 'status' => SponsorInquiry::STATUS_CLOSED]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sponsor requests')
            ->assertSee('1 new')
            ->assertSee('Green Line Travels')
            ->assertDontSee('Old Request');
    }

    public function test_admins_read_filter_and_answer_requests(): void
    {
        $open = $this->inquiry(['company' => 'Green Line Travels']);
        $this->inquiry(['company' => 'Cha Corner', 'email' => 'hello@cha.test', 'status' => SponsorInquiry::STATUS_CLOSED]);

        $this->actingAs($this->admin());

        // Open requests by default; closed ones under their own filter.
        $this->get(route('admin.sponsor-inquiries.index'))->assertOk()->assertSee('Green Line Travels')->assertDontSee('Cha Corner');
        $this->get(route('admin.sponsor-inquiries.index', ['status' => 'closed']))->assertSee('Cha Corner')->assertDontSee('Green Line Travels');
        $this->get(route('admin.sponsor-inquiries.index', ['status' => 'all', 'q' => 'cha.test']))->assertSee('Cha Corner')->assertDontSee('Green Line Travels');

        $this->get(route('admin.sponsor-inquiries.show', $open))
            ->assertOk()
            ->assertSee('We would love to sponsor the home page this winter.')
            ->assertSee('mailto:nadia@greenline.test', false);

        $this->put(route('admin.sponsor-inquiries.update', $open), ['status' => 'contacted'])->assertRedirect();
        $this->assertSame(SponsorInquiry::STATUS_CONTACTED, $open->refresh()->status);

        $this->put(route('admin.sponsor-inquiries.update', $open), ['status' => 'lost'])->assertSessionHasErrors('status');

        $this->delete(route('admin.sponsor-inquiries.destroy', $open))->assertRedirect(route('admin.sponsor-inquiries.index'));
        $this->assertModelMissing($open);
    }
}
