<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\MaintenanceMode;
use App\Services\PhoneCodes;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * /api/v1, the Android app's side of Sofor: token sign-in, money in poisha,
 * retries that never save twice, and the same rules as the website.
 */
class AppApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Trip, 1: User, 2: User, 3: \Illuminate\Support\Collection<int, TripMember>} */
    private function tripOfTwo(): array
    {
        $leader = User::factory()->create(['name' => 'Rifat Leader']);
        $friend = User::factory()->create(['name' => 'Nusrat Jahan']);
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => 'Sajek cloud trip', 'currency' => 'BDT']);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->invite($trip, $leader, ['name' => $friend->name, 'email' => $friend->email]);

        return [$trip, $leader, $friend, $trip->members()->get()];
    }

    /** @return array<string, mixed> */
    private function cost(\Illuminate\Support\Collection $members, array $more = []): array
    {
        return $more + [
            'title' => 'Hill cottage',
            'amount' => 400050,
            'category' => 'accommodation',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ];
    }

    public function test_signing_in_gives_a_token_that_opens_the_api(): void
    {
        User::factory()->create(['email' => 'rafi@example.com', 'name' => 'Rafi']);

        $this->getJson('/api/v1/me')->assertUnauthorized();

        $token = $this->postJson('/api/v1/auth/login', ['login' => 'rafi@example.com', 'password' => 'password', 'device_name' => 'Pixel 7'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Rafi')
            ->assertJsonPath('user.verified', true)
            ->json('token');

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Pixel 7']);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.email', 'rafi@example.com');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_wrong_password_is_refused_like_on_the_website(): void
    {
        User::factory()->create(['email' => 'rafi@example.com']);

        $this->postJson('/api/v1/auth/login', ['login' => 'rafi@example.com', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('login');
    }

    public function test_admins_and_paused_accounts_are_kept_out_of_the_app(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/v1/auth/login', ['login' => 'admin@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_account');

        // Paused after signing in: the next request signs the phone out.
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'account_paused');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_signing_up_in_the_app_then_proving_the_phone(): void
    {
        User::factory()->admin()->create();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Rahman',
            'contact_type' => 'phone',
            'phone' => '01712345678',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => true,
        ])->assertCreated()->assertJsonPath('user.verified', false);

        $token = $response->json('token');
        $user = User::where('phone', '+8801712345678')->firstOrFail();
        $this->assertNotEmpty($user->username);
        $this->assertSame('user', $user->role);

        // Not verified yet: the traveller app stays shut, with a code the app understands.
        $this->withToken($token)->getJson('/api/v1/trips')->assertForbidden()->assertJsonPath('code', 'contact_unverified');

        $this->withToken($token)->postJson('/api/v1/me/phone/verify', ['code' => '000000'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        // The real code: the hash of what was texted is all that is stored, so plant one.
        VerificationCode::query()->delete();
        $code = $this->freshPhoneCode($user);

        $this->withToken($token)->postJson('/api/v1/me/phone/verify', ['code' => $code])
            ->assertOk()->assertJsonPath('user.phone_verified', true);

        $this->withToken($token)->getJson('/api/v1/trips')->assertOk();
    }

    /** Sends a code through the real service and reads it from the log driver's last text. */
    private function freshPhoneCode(User $user): string
    {
        $sms = \Mockery::spy(\App\Services\SmsService::class);
        $this->app->instance(\App\Services\SmsService::class, $sms);
        $this->app->forgetInstance(PhoneCodes::class);

        app(PhoneCodes::class)->send(VerificationCode::PURPOSE_PHONE, $user->phone, $user);

        $text = '';
        $sms->shouldHaveReceived('send')->withArgs(function ($to, $message) use (&$text) {
            $text = $message;

            return true;
        });

        preg_match('/\b(\d{6})\b/', $text, $match);

        return $match[1];
    }

    public function test_money_travels_as_whole_poisha_and_plain_digits(): void
    {
        [$trip, $leader, , $members] = $this->tripOfTwo();
        Sanctum::actingAs($leader);

        $response = $this->withHeader('Accept-Language', 'bn')
            ->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))
            ->assertCreated()
            ->assertJsonPath('expenses.0.amount', 400050)
            ->assertJsonPath('stats.spent', 400050)
            ->assertJsonPath('expenses.0.can_modify', true);

        $this->assertSame('4000.50', Expense::firstOrFail()->amount);

        // 4000.50 between two: 2000.25 each, and the friend owes it to the leader.
        $response->assertJsonPath('suggestions.0.amount', 200025)
            ->assertJsonPath('me.credit', 200025);

        // Bangla words, never Bangla digits: the app formats numbers itself.
        $this->assertDoesNotMatchRegularExpression('/[০-৯]/u', $response->getContent());
        $this->assertMatchesRegularExpression('/[\x{0980}-\x{09FF}]/u', (string) $response->json('message'));
    }

    public function test_a_cost_sent_twice_from_a_phone_with_bad_signal_is_saved_once(): void
    {
        [$trip, $leader, , $members] = $this->tripOfTwo();
        Sanctum::actingAs($leader);

        $uuid = (string) Str::uuid();

        $first = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members, ['client_uuid' => $uuid]))->assertCreated();
        $again = $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members, ['client_uuid' => $uuid]))->assertOk();

        $this->assertSame($first->json('id'), $again->json('id'));
        $this->assertSame(1, Expense::count());
    }

    public function test_a_payment_is_recorded_confirmed_and_never_doubled(): void
    {
        [$trip, $leader, $friend, $members] = $this->tripOfTwo();

        Sanctum::actingAs($leader);
        $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))->assertCreated();

        // The friend reports paying what they owe; the leader has to confirm it.
        Sanctum::actingAs($friend);
        $uuid = (string) Str::uuid();
        $payment = ['amount' => 200025, 'method' => 'bkash', 'reference' => 'TRX123', 'client_uuid' => $uuid];

        $this->postJson("/api/v1/trips/{$trip->id}/settlements", $payment)
            ->assertCreated()
            ->assertJsonPath('settlements.0.amount', 200025)
            ->assertJsonPath('settlements.0.pending', true)
            ->assertJsonPath('settlements.0.can_confirm', false);

        $this->postJson("/api/v1/trips/{$trip->id}/settlements", $payment)->assertOk();
        $this->assertSame(1, Settlement::count());

        $settlement = Settlement::firstOrFail();
        $this->postJson("/api/v1/trips/{$trip->id}/settlements/{$settlement->id}/confirm")->assertForbidden();

        Sanctum::actingAs($leader);
        $this->postJson("/api/v1/trips/{$trip->id}/settlements/{$settlement->id}/confirm")
            ->assertOk()
            ->assertJsonPath('settlements.0.pending', false)
            ->assertJsonPath('stats.outstanding', 0);
    }

    public function test_a_trip_is_created_listed_and_only_downloaded_when_it_changed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/trips', [
            'title' => 'Sylhet tea trip',
            'destination' => 'Sreemangal',
            'cover' => 'mint',
            'icon' => 'tea',
            'currency' => 'BDT',
            'budget' => 3000000,
            'budget_mode' => 'group',
            'members' => [['name' => 'Tuhin']],
        ])->assertCreated()
            ->assertJsonPath('trip.title', 'Sylhet tea trip')
            ->assertJsonPath('trip.budget', 3000000)
            ->assertJsonPath('trip.is_owner', true)
            ->assertJsonCount(2, 'members')
            ->assertJsonPath('can.add_expense', true);

        $trip = Trip::firstOrFail();
        $this->assertSame('30000.00', $trip->budget);

        $this->getJson('/api/v1/trips')->assertOk()->assertJsonPath('trips.0.id', $trip->id)->assertJsonPath('trips.0.member_count', 2);

        $stamp = $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()->json('stamp');
        $this->getJson("/api/v1/trips/{$trip->id}?stamp={$stamp}")->assertStatus(304);

        $this->postJson("/api/v1/trips/{$trip->id}/end")->assertOk()->assertJsonPath('trip.status', 'ended')->assertJsonPath('can.add_expense', false);
    }

    public function test_only_travellers_on_a_trip_can_read_it_and_only_the_leader_manages_it(): void
    {
        [$trip, , $friend, $members] = $this->tripOfTwo();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/trips/{$trip->id}")->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))->assertForbidden();

        Sanctum::actingAs($friend);
        $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()->assertJsonPath('can.manage_members', false);
        $this->postJson("/api/v1/trips/{$trip->id}/members", ['name' => 'Gatecrasher'])->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/end")->assertForbidden();
        $this->deleteJson("/api/v1/trips/{$trip->id}")->assertForbidden();

        // A cost on another trip cannot be reached through this one.
        $other = Trip::factory()->create();
        $this->getJson("/api/v1/trips/{$other->id}/expenses/1/receipt")->assertNotFound();
    }

    public function test_joining_from_an_invite_link(): void
    {
        [$trip] = $this->tripOfTwo();
        Sanctum::actingAs(User::factory()->create(['name' => 'Late Joiner']));

        $this->getJson("/api/v1/join/{$trip->invite_code}")
            ->assertOk()
            ->assertJsonPath('trip.title', 'Sajek cloud trip')
            ->assertJsonPath('already_member', false);

        $this->postJson("/api/v1/join/{$trip->invite_code}")->assertCreated()->assertJsonCount(3, 'members');
        $this->postJson("/api/v1/join/{$trip->invite_code}")->assertOk();

        $this->assertSame(3, $trip->members()->count());
    }

    public function test_payout_numbers_and_alerts(): void
    {
        [$trip, $leader, $friend, $members] = $this->tripOfTwo();

        Sanctum::actingAs($leader);
        $this->postJson('/api/v1/payout-methods', ['provider' => 'bkash', 'account_number' => '01712345678'])
            ->assertCreated()
            ->assertJsonPath('payout_methods.0.number', '01712345678')
            ->assertJsonPath('payout_methods.0.is_primary', true);

        $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))->assertCreated();

        // The friend sees where to pay the leader, and was told about the cost.
        Sanctum::actingAs($friend);
        $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()->assertJsonPath('members.0.payout_methods.0.provider', 'bkash');

        // Two alerts: being added to the trip, and the cost.
        $alerts = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread', 2);
        $this->assertSame($trip->id, $alerts->json('data.0.trip_id'));

        $this->postJson('/api/v1/notifications/'.$alerts->json('data.0.id').'/read')->assertOk()->assertJsonPath('unread', 1);
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);

        $this->getJson('/api/v1/balances')->assertOk()->assertJsonPath('friends.0.net', -200025);
    }

    public function test_a_phone_registers_for_push_alerts_and_follows_whoever_signs_in(): void
    {
        $token = str_repeat('fcm-token-', 16);
        $first = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($first);
        $this->postJson('/api/v1/devices', ['token' => $token, 'app_version' => '1.0.0'])->assertOk();
        $this->assertSame($first->id, DeviceToken::firstOrFail()->user_id);

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/devices', ['token' => $token])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertSame($second->id, DeviceToken::firstOrFail()->user_id);

        $this->deleteJson('/api/v1/devices', ['token' => $token])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_an_alert_is_pushed_to_the_phone_in_its_owners_language(): void
    {
        [$trip, $leader, $friend, $members] = $this->tripOfTwo();
        $friend->forceFill(['locale' => 'bn'])->save();
        DeviceToken::register($friend, str_repeat('live-token-', 4));
        DeviceToken::register($friend, str_repeat('dead-token-', 4));

        $sent = [];
        $fcm = \Mockery::mock(\App\Services\Fcm::class);
        $fcm->shouldReceive('configured')->andReturn(true);
        $fcm->shouldReceive('send')->andReturnUsing(function (array $tokens, string $title, string $body, array $data) use (&$sent) {
            $sent[] = compact('tokens', 'title', 'body', 'data');

            // Firebase says the second phone removed the app.
            return [str_repeat('dead-token-', 4)];
        });
        $this->app->instance(\App\Services\Fcm::class, $fcm);

        Sanctum::actingAs($leader);
        $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))->assertCreated();

        // Only the friend has the app; the leader made the change and is not told about it.
        $this->assertCount(1, $sent);
        $this->assertCount(2, $sent[0]['tokens']);
        $this->assertSame($trip->id, $sent[0]['data']['trip_id']);
        $this->assertMatchesRegularExpression('/[০-৯]/u', $sent[0]['title'].$sent[0]['body']);
        $this->assertDoesNotMatchRegularExpression('/[0-9]/', $sent[0]['body']);

        $this->assertSame(1, DeviceToken::count());
    }

    public function test_the_code_text_ends_with_the_apps_hash_so_android_fills_it_in(): void
    {
        config(['services.android.sms_hash' => 'FA+9qCX9VSu']);
        $user = User::factory()->unverified()->create(['phone' => '+8801712345678']);

        $sms = \Mockery::spy(\App\Services\SmsService::class);
        $this->app->instance(\App\Services\SmsService::class, $sms);

        app(PhoneCodes::class)->send(VerificationCode::PURPOSE_PHONE, $user->phone, $user);

        $sms->shouldHaveReceived('send')->withArgs(fn ($to, $text) => str_ends_with($text, "\nFA+9qCX9VSu") && strlen($text) <= 160);
    }

    public function test_the_api_closes_with_the_site_and_refuses_builds_that_are_too_old(): void
    {
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('covers.0.key', 'aurora')->assertJsonStructure(['categories', 'payment_methods', 'links' => ['delete_account']]);

        config(['services.android.min_version_code' => 10]);
        $this->withHeader('X-Sofor-App', 'android/9')->getJson('/api/v1/config')->assertStatus(426)->assertJsonPath('code', 'update_required');
        $this->withHeader('X-Sofor-App', 'android/10')->getJson('/api/v1/config')->assertOk();

        app(MaintenanceMode::class)->enable(User::factory()->admin()->create(), 'Back at nine.');
        $this->withHeader('X-Sofor-App', 'android/10')->getJson('/api/v1/config')->assertStatus(503);
    }

    public function test_deleting_an_account_keeps_shared_trips_right_for_everyone_else(): void
    {
        [$trip, $leader, $friend, $members] = $this->tripOfTwo();
        $solo = Trip::factory()->create(['user_id' => $friend->id]);
        app(TripInvitationService::class)->ensureOwnerMember($solo, $friend);

        Sanctum::actingAs($leader);
        $this->postJson("/api/v1/trips/{$trip->id}/expenses", $this->cost($members))->assertCreated();

        Sanctum::actingAs($friend);
        $friend->createToken('phone');

        $this->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertStatus(422);
        $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertOk();

        $friend->refresh();
        $this->assertNull($friend->email);
        $this->assertNull($friend->phone);
        $this->assertFalse($friend->is_active);
        $this->assertNotNull($friend->deleted_at);
        $this->assertSame(0, $friend->tokens()->count());

        // Their own trip is gone; the shared one still shows the same debt, with no name on it.
        $this->assertModelMissing($solo);
        $seat = $members->last()->refresh();
        $this->assertNull($seat->user_id);
        $this->assertNull($seat->email);
        $this->assertNotSame('Nusrat Jahan', $seat->name);

        Sanctum::actingAs($leader);
        $this->getJson("/api/v1/trips/{$trip->id}")->assertOk()->assertJsonPath('stats.outstanding', 200025);

        // And nobody signs in to it again.
        $this->postJson('/api/v1/auth/login', ['login' => 'nusrat@example.com', 'password' => 'password'])->assertStatus(422);
    }

    public function test_the_website_also_lets_a_traveller_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->get(route('account.delete'))->assertOk()->assertSee('Deleted traveller');
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee(route('profile.destroy'), false);

        $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), ['delete_password' => 'nope'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasErrors('delete_password');

        $this->actingAs($user)->delete(route('profile.destroy'), ['delete_password' => 'password'])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotNull($user->refresh()->deleted_at);
    }

    public function test_android_is_told_which_links_belong_to_the_app(): void
    {
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

        config(['services.android.sha256' => ['AA:BB']]);

        $this->getJson('/.well-known/assetlinks.json')->assertOk()
            ->assertJsonPath('0.target.package_name', 'io.sofor.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB');
    }
}
