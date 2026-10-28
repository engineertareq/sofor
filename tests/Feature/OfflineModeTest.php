<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Costs logged without signal are queued on the phone and sent later - once. */
class OfflineModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cost_sent_twice_from_the_offline_queue_is_saved_once(): void
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);
        $uuid = (string) Str::uuid();

        $cost = [
            'title' => 'Tea at Ruilui', 'amount' => 120, 'category' => 'food', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->value('id'), 'split_type' => 'equal', 'client_uuid' => $uuid,
        ];

        $this->actingAs($leader)->postJson(route('trips.expenses.store', $trip), $cost)->assertOk()->assertJsonMissing(['duplicate' => true]);
        $this->postJson(route('trips.expenses.store', $trip), $cost)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Expense::count());
        $this->assertSame($uuid, Expense::first()->client_uuid);

        $this->postJson(route('trips.expenses.store', $trip), ['client_uuid' => 'not-a-uuid'] + $cost)->assertJsonValidationErrors('client_uuid');
    }

    public function test_the_queue_gets_a_fresh_token_and_the_offline_page_stands_alone(): void
    {
        $this->getJson(route('session.token'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson(route('session.token'))
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $page = $this->get(route('offline'))->assertOk();
        $page->assertSee('No connection right now');
        $this->assertStringNotContainsString('/build/', $page->getContent(), 'The offline page must not depend on files that may not be cached.');

        $this->assertFileExists(public_path('sw.js'));
    }
}
