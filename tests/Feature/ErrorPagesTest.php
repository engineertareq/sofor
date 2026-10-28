<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sofor's own error pages: they render inside the site's look, know who is
 * signed in, and always offer a working way back.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_address_shows_the_custom_404_with_a_way_home(): void
    {
        $this->get('/somewhere/that-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page took a wrong turn')
            ->assertSee('/somewhere/that-does-not-exist')
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('Go to the home page');
    }

    public function test_the_404_knows_who_is_signed_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Back to my trips')
            ->assertSee(route('dashboard'), false);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Go to the console')
            ->assertSee(route('admin.dashboard'), false);
    }

    public function test_a_missing_record_uses_the_same_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('trips.show', 999_999))
            ->assertNotFound()
            ->assertSee('This page took a wrong turn');
    }

    public function test_api_style_requests_still_get_json(): void
    {
        $this->getJson('/no-such-page')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_a_forbidden_page_explains_itself(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('This page is not for you')
            ->assertSee('This area is for Sofor administrators.')
            ->assertSee('Back to my trips');
    }

    public function test_a_crash_shows_a_friendly_page_without_internals(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/__boom', fn () => throw new \RuntimeException('database password is hunter2'));

        $this->get('/__boom')
            ->assertStatus(500)
            ->assertSee('Something broke on our side')
            ->assertDontSee('hunter2');
    }
}
