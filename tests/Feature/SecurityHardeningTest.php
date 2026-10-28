<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the security review: headers, who may change which
 * money records, paused accounts, payment checks and output encoding.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Trip, 1: User, 2: User, 3: User} trip, leader, and two travellers */
    private function trip(): array
    {
        $leader = User::factory()->create(['name' => 'Leader']);
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'currency' => 'BDT']);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);

        $a = User::factory()->create(['name' => 'Asha']);
        $b = User::factory()->create(['name' => 'Bilal']);
        $invitations->invite($trip, $leader, ['name' => $a->name, 'email' => $a->email]);
        $invitations->invite($trip, $leader, ['name' => $b->name, 'email' => $b->email]);

        return [$trip->refresh(), $leader, $a, $b];
    }

    private function memberId(Trip $trip, User $user): int
    {
        return (int) $trip->members()->where('user_id', $user->id)->value('id');
    }

    private function logCost(Trip $trip, User $by, array $overrides = [])
    {
        return $this->actingAs($by)->post(route('trips.expenses.store', $trip), $overrides + [
            'title' => 'Dinner',
            'amount' => 900,
            'category' => 'food',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->memberId($trip, $by),
            'split_type' => 'equal',
            'participant_ids' => $trip->members()->pluck('id')->all(),
        ]);
    }

    /* ------------------------------------------------------------------
       Headers
    ------------------------------------------------------------------ */

    public function test_every_response_carries_the_security_headers(): void
    {
        foreach ([$this->get(route('home')), $this->get('/no-such-page')] as $response) {
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
        }
    }

    /* ------------------------------------------------------------------
       Costs
    ------------------------------------------------------------------ */

    public function test_only_the_leader_or_the_author_can_change_a_cost(): void
    {
        [$trip, $leader, $a, $b] = $this->trip();
        $this->logCost($trip, $a)->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();

        $edit = ['title' => 'Changed', 'amount' => 1, 'category' => 'food', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->memberId($trip, $a), 'split_type' => 'equal', 'participant_ids' => [$this->memberId($trip, $a)]];

        // Another traveller: refused, both ways.
        $this->actingAs($b)->put(route('trips.expenses.update', [$trip, $expense]), $edit)->assertForbidden();
        $this->actingAs($b)->delete(route('trips.expenses.destroy', [$trip, $expense]))->assertForbidden();
        $this->assertSame('Dinner', $expense->refresh()->title);

        // The author and the leader may.
        $this->actingAs($a)->put(route('trips.expenses.update', [$trip, $expense]), $edit)->assertRedirect();
        $this->assertSame('Changed', $expense->refresh()->title);
        $this->actingAs($leader)->delete(route('trips.expenses.destroy', [$trip, $expense]))->assertRedirect();
        $this->assertSoftDeleted($expense);
    }

    public function test_a_removed_traveller_cannot_be_put_on_a_new_cost(): void
    {
        [$trip, $leader, $a] = $this->trip();
        $member = TripMember::find($this->memberId($trip, $a));
        $member->update(['status' => 'removed']);

        $this->logCost($trip, $leader, ['trip_member_id' => $member->id])->assertSessionHasErrors('trip_member_id');
        $this->logCost($trip, $leader, ['participant_ids' => [$member->id]])->assertSessionHasErrors('participant_ids');
    }

    public function test_the_currency_is_locked_once_money_is_logged(): void
    {
        [$trip, $leader] = $this->trip();
        $this->logCost($trip, $leader);

        $this->actingAs($leader)->put(route('trips.update', $trip), [
            'title' => $trip->title, 'cover' => $trip->cover, 'currency' => 'USD',
        ])->assertSessionHasErrors('currency');

        $this->assertSame('BDT', $trip->refresh()->currency);
    }

    /* ------------------------------------------------------------------
       Payments
    ------------------------------------------------------------------ */

    public function test_a_traveller_only_records_payments_they_took_part_in(): void
    {
        [$trip, $leader, $a, $b] = $this->trip();
        $payment = fn (User $from, User $to) => ['from_member_id' => $this->memberId($trip, $from), 'to_member_id' => $this->memberId($trip, $to),
            'amount' => 100, 'method' => 'cash', 'settled_on' => now()->toDateString()];

        // A transfer between two other people.
        $this->actingAs($b)->post(route('trips.settlements.store', $trip), $payment($a, $leader))->assertSessionHasErrors('from_member_id');
        $this->assertSame(0, Settlement::count());

        // Their own, either side; and the leader may record anything.
        $this->actingAs($b)->post(route('trips.settlements.store', $trip), $payment($b, $leader))->assertSessionHasNoErrors();
        $this->actingAs($b)->post(route('trips.settlements.store', $trip), $payment($a, $b))->assertSessionHasNoErrors();
        $this->actingAs($leader)->post(route('trips.settlements.store', $trip), $payment($a, $b))->assertSessionHasNoErrors();
        $this->assertSame(3, Settlement::count());
    }

    public function test_payment_records_are_removed_by_their_recorder_or_the_leader_but_bkash_ones_never(): void
    {
        [$trip, $leader, $a, $b] = $this->trip();
        $this->actingAs($a)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $this->memberId($trip, $a), 'to_member_id' => $this->memberId($trip, $leader),
            'amount' => 100, 'method' => 'cash', 'settled_on' => now()->toDateString(),
        ]);
        $manual = Settlement::firstOrFail();

        $this->actingAs($b)->delete(route('trips.settlements.destroy', [$trip, $manual]))->assertForbidden();
        $this->actingAs($a)->delete(route('trips.settlements.destroy', [$trip, $manual]))->assertRedirect();
        $this->assertModelMissing($manual);

        $verified = Settlement::create([
            'trip_id' => $trip->id, 'from_member_id' => $this->memberId($trip, $a), 'to_member_id' => $this->memberId($trip, $leader),
            'amount' => 500, 'method' => 'bkash', 'gateway' => 'bkash', 'gateway_trx_id' => 'BKA123', 'recorded_by' => $a->id, 'settled_on' => now(),
        ]);

        $this->actingAs($leader)->delete(route('trips.settlements.destroy', [$trip, $verified]))->assertForbidden();
        $this->actingAs($a)->delete(route('trips.settlements.destroy', [$trip, $verified]))->assertForbidden();

        // And the trip is kept as the record: the leader can end it, not delete it.
        $this->actingAs($leader)->get(route('trips.show', $trip))->assertDontSee('Delete trip')->assertSee('kept as their record');
        $this->actingAs($leader)->delete(route('trips.destroy', $trip))->assertForbidden();
        $this->assertModelExists($verified);
    }

    /* ------------------------------------------------------------------
       Accounts
    ------------------------------------------------------------------ */

    public function test_a_paused_account_is_signed_out_at_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_access_fields_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'role' => 'admin', 'is_active' => false, 'email_verified_at' => now(), 'phone_verified_at' => now()]);

        $this->assertNull($user->role);
        $this->assertNull($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_changing_the_password_retires_the_remember_me_token(): void
    {
        $user = User::factory()->create(['password' => 'oldpass123']);
        $token = $user->remember_token;

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'oldpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($token, $user->refresh()->remember_token);
    }

    public function test_a_notification_never_redirects_off_site(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['url' => 'https://evil.example/phish'],
        ]);
        $id = $user->notifications()->value('id');

        $this->actingAs($user)->post(route('notifications.read', $id))->assertRedirect(route('dashboard'));
    }

    /* ------------------------------------------------------------------
       Output encoding
    ------------------------------------------------------------------ */

    public function test_names_and_notes_are_encoded_for_javascript(): void
    {
        [$trip, $leader] = $this->trip();
        $this->logCost($trip, $leader, ['title' => "It's \"fine\"", 'notes' => "line one\nline two '); alert(1); //"]);

        $html = $this->actingAs($leader)->get(route('trips.show', $trip))->assertOk()->getContent();

        // JSON encoded with quotes escaped, so the text can never close the string it sits in.
        $this->assertStringContainsString('notes: \'line one\\nline two \\u0027); alert(1); \\/\\/\'', html_entity_decode($html, ENT_QUOTES));
        $this->assertStringNotContainsString("notes: 'line one\nline two", html_entity_decode($html, ENT_QUOTES));
    }

    public function test_a_name_cannot_smuggle_a_link_into_an_email(): void
    {
        $user = User::factory()->unverified()->create(['name' => '[Claim-prize](https://evil.example) Rahman']);

        $html = (string) (new VerifyEmailAddress('123456'))->toMail($user)->render();

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('](', $html);
    }
}
