<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\BrandingService;
use App\Services\MaintenanceMode;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin -> Settings -> Maintenance closes the whole site to everyone but
 * administrators, and opens it again just as easily.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function turnOn(User $admin, ?string $message = 'Upgrading payments. Back by 6pm.'): void
    {
        $this->actingAs($admin)
            ->from(route('admin.maintenance'))
            ->put(route('admin.maintenance.update'), ['action' => 'on', 'message' => $message])
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHasNoErrors();

        auth()->logout();
    }

    public function test_only_admins_can_switch_it(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->get(route('admin.maintenance'))->assertForbidden();
        $this->actingAs($traveller)->put(route('admin.maintenance.update'), ['action' => 'on'])->assertForbidden();

        $this->assertFalse(app(MaintenanceMode::class)->active());
    }

    public function test_nobody_but_admins_can_open_anything_while_it_is_on(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ops Admin']);
        $traveller = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $traveller->id]);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $traveller);

        $this->turnOn($admin);

        // Guests: the public site, sign up, invite links, unknown and missing pages.
        foreach ([route('home'), route('register'), route('about'), route('support.index'),
            route('trips.join.show', $trip->invite_code), '/no-such-page'] as $url) {
            $this->get($url)
                ->assertStatus(503)
                ->assertHeader('Retry-After', '300')
                ->assertSee('Sofor is getting a tune-up')
                ->assertSee('Upgrading payments. Back by 6pm.')
                ->assertSee('Administrator sign in');
        }

        // A signed in traveller, including their own trip and the live sync it polls.
        $this->actingAs($traveller)->get(route('dashboard'))->assertStatus(503)->assertDontSee('Administrator sign in');
        $this->actingAs($traveller)->get(route('trips.show', $trip))->assertStatus(503);
        $this->actingAs($traveller)->get(route('trips.show', 999_999))->assertStatus(503);
        $this->actingAs($traveller)->getJson(route('trips.live', $trip))->assertStatus(503)->assertJsonStructure(['message']);
        $this->actingAs($traveller)->post(route('trips.store'), ['title' => 'Sneaky', 'cover' => 'aurora', 'currency' => 'BDT'])->assertStatus(503);
        $this->assertSame(1, Trip::count());

        // The admin keeps the whole console, with a reminder on every page.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Maintenance mode is on');
        $this->actingAs($admin)->get(route('admin.trips.show', $trip))->assertOk();
        $this->actingAs($admin)->get(route('admin.maintenance'))
            ->assertOk()
            ->assertSee('Sofor is closed for maintenance')
            ->assertSee('turned on by Ops Admin');
    }

    public function test_the_sign_in_page_stays_open_but_only_admins_get_in(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        User::factory()->create(['email' => 'rifat@example.com']);

        $this->turnOn($admin);

        $this->get(route('login'))->assertOk()->assertSee('Sofor is down for maintenance');

        $this->post(route('login.store'), ['login' => 'rifat@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post(route('login.store'), ['login' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_maintenance_page_still_shows_the_uploaded_logo(): void
    {
        Storage::fake(BrandingService::DISK);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 600, 120),
        ]);
        $this->turnOn($admin);

        $logo = app(BrandingService::class)->logoUrl();

        $this->get(route('home'))->assertStatus(503)->assertSee($logo, false);
        $this->get($logo)->assertOk();
    }

    public function test_turning_it_off_reopens_everything(): void
    {
        $admin = User::factory()->admin()->create();
        $this->turnOn($admin);

        $this->get(route('home'))->assertStatus(503);

        $this->actingAs($admin)
            ->put(route('admin.maintenance.update'), ['action' => 'off'])
            ->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('home'))->assertOk();
        $this->get('/no-such-page')->assertNotFound();
        $this->assertNull(app(MaintenanceMode::class)->since());
    }

    public function test_the_message_can_change_without_resetting_the_start_time(): void
    {
        $admin = User::factory()->admin()->create();

        $this->travelTo(now()->subHour());
        $this->turnOn($admin, 'First note');
        $this->travelBack();

        $this->turnOn($admin, 'Nearly done');

        $maintenance = app(MaintenanceMode::class);
        $this->assertSame('Nearly done', $maintenance->message());
        $this->assertTrue($maintenance->since()->lt(now()->subMinutes(50)));

        $this->actingAs($admin)
            ->put(route('admin.maintenance.update'), ['action' => 'on', 'message' => str_repeat('x', MaintenanceMode::MESSAGE_MAX + 1)])
            ->assertSessionHasErrors('message');
    }

    public function test_admins_can_preview_the_page_without_closing_the_site(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.maintenance.preview'))
            ->assertOk()
            ->assertSee('Preview - this is what visitors see')
            ->assertSee('Sofor is getting a tune-up');

        auth()->logout();
        $this->get(route('home'))->assertOk();
    }
}
