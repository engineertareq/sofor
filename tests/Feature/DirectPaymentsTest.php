<?php

namespace Tests\Feature;

use App\Exceptions\SmsWalletException;
use App\Models\PaymentReminder;
use App\Models\PayoutMethod;
use App\Models\Settlement;
use App\Models\SmsTransaction;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\PaymentDueReminder;
use App\Notifications\TripNotification;
use App\Services\SmsWallet;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Trip dues are paid person to person into each traveller's own bKash,
 * Nagad, Rocket, Upay or bank account. Payers report, receivers confirm;
 * Sofor nudges (free in-app and email, texts from the sender's SMS wallet)
 * and gives travellers without an account a pay link.
 */
class DirectPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private int $gatewayError = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.driver' => 'smsnetbd',
            'services.sms.smsnetbd.url' => 'https://api.sms.test',
            'services.sms.smsnetbd.key' => 'test-key',
            'services.sms.price' => 0.45,
        ]);

        Http::fake(['api.sms.test/*' => fn () => Http::response(['error' => $this->gatewayError, 'msg' => 'ok', 'data' => ['request_id' => 1]])]);
    }

    /**
     * Leader paid 900 for three people: Asha and Bilal owe 300 each.
     *
     * @return array{0: Trip, 1: User, 2: User, 3: User}
     */
    private function trip(): array
    {
        $leader = User::factory()->create(['name' => 'Rifat Leader']);
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => 'Sajek trip']);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);

        $asha = User::factory()->create(['name' => 'Asha Rahman', 'phone' => '+8801711111111']);
        $bilal = User::factory()->create(['name' => 'Bilal Khan']);
        $invitations->invite($trip, $leader, ['name' => $asha->name, 'email' => $asha->email]);
        $invitations->invite($trip, $leader, ['name' => $bilal->name, 'email' => $bilal->email]);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cottage', 'amount' => 900, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $this->member($trip, $leader)->id, 'split_type' => 'equal',
            'participant_ids' => $trip->members()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        return [$trip->refresh(), $leader, $asha, $bilal];
    }

    private function member(Trip $trip, User $user): TripMember
    {
        return $trip->members()->where('user_id', $user->id)->firstOrFail();
    }

    /* ------------------------------------------------------------------
       How friends pay you
    ------------------------------------------------------------------ */

    public function test_a_traveller_adds_where_they_want_to_be_paid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '017-1234-5678'])->assertSessionHasNoErrors();
        $this->post(route('payout-methods.store'), ['provider' => 'nagad', 'account_number' => '01812345678'])->assertSessionHasNoErrors();
        $this->post(route('payout-methods.store'), ['provider' => 'rocket', 'account_number' => '017123456789'])->assertSessionHasNoErrors();
        $this->post(route('payout-methods.store'), ['provider' => 'bank', 'account_number' => '1234567890', 'account_name' => 'Rifat Hasan', 'bank_name' => 'Dutch-Bangla Bank'])->assertSessionHasNoErrors();

        $methods = $user->payoutMethods()->get();
        $this->assertCount(4, $methods);
        $this->assertTrue($methods->first()->is_primary);
        $this->assertSame('01712-345678', $methods->first()->displayNumber());
        $this->assertSame('01712345678', $methods->first()->copyValue());

        // Nonsense is refused, per provider.
        $this->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '12345'])->assertSessionHasErrors('account_number');
        $this->post(route('payout-methods.store'), ['provider' => 'rocket', 'account_number' => '01712345678'])->assertSessionHasErrors('account_number');
        $this->post(route('payout-methods.store'), ['provider' => 'bank', 'account_number' => '1234567890'])->assertSessionHasErrors(['account_name', 'bank_name']);
        $this->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '01712345678'])->assertSessionHasErrors('account_number');

        $this->get(route('profile.edit'))->assertOk()->assertSee('How friends pay you')->assertSee('01712-345678');
    }

    public function test_the_main_account_moves_and_nobody_else_can_touch_yours(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '01712345678']);
        $this->post(route('payout-methods.store'), ['provider' => 'nagad', 'account_number' => '01812345678']);
        [$bkash, $nagad] = $user->payoutMethods()->get()->all();

        $this->post(route('payout-methods.primary', $nagad))->assertRedirect();
        $this->assertTrue($nagad->refresh()->is_primary);
        $this->assertFalse($bkash->refresh()->is_primary);

        $this->delete(route('payout-methods.destroy', $nagad));
        $this->assertTrue($bkash->refresh()->is_primary);

        $this->actingAs(User::factory()->create())->delete(route('payout-methods.destroy', $bkash))->assertNotFound();
        $this->assertModelExists($bkash);
    }

    /* ------------------------------------------------------------------
       Paying and confirming
    ------------------------------------------------------------------ */

    public function test_the_debtor_sees_where_to_pay_the_leader(): void
    {
        [$trip, $leader, $asha] = $this->trip();
        $this->actingAs($leader)->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '01999888777']);

        $this->actingAs($asha)->get(route('trips.show', $trip))
            ->assertOk()
            ->assertSee('Your share still open')
            ->assertSee('01999-888777')
            ->assertSee('I paid Rifat Leader')
            ->assertDontSee('Pay with bKash');
    }

    public function test_a_reported_payment_waits_for_the_receiver_to_confirm(): void
    {
        Notification::fake();
        [$trip, $leader, $asha, $bilal] = $this->trip();

        $this->actingAs($asha)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $this->member($trip, $asha)->id, 'to_member_id' => $this->member($trip, $leader)->id,
            'amount' => 300, 'method' => 'nagad', 'reference' => 'NG77X', 'settled_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $payment = Settlement::firstOrFail();
        $this->assertTrue($payment->isPending());
        Notification::assertSentTo($leader, TripNotification::class);

        // Only the receiver (or the leader) can say whether it arrived.
        $this->actingAs($bilal)->post(route('trips.settlements.confirm', [$trip, $payment]))->assertForbidden();
        $this->actingAs($asha)->post(route('trips.settlements.confirm', [$trip, $payment]))->assertForbidden();

        $this->actingAs($leader)->get(route('trips.show', $trip))->assertSee('says they paid you')->assertSee('NG77X');
        $this->post(route('trips.settlements.confirm', [$trip, $payment]))->assertRedirect();

        $this->assertFalse($payment->refresh()->isPending());
        $this->assertSame($leader->id, $payment->confirmed_by);
        Notification::assertSentTo($asha, TripNotification::class);
    }

    public function test_a_payment_that_never_arrived_comes_off_the_ledger(): void
    {
        [$trip, $leader, $asha] = $this->trip();

        $this->actingAs($asha)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $this->member($trip, $asha)->id, 'to_member_id' => $this->member($trip, $leader)->id,
            'amount' => 300, 'method' => 'bkash', 'settled_on' => now()->toDateString(),
        ]);
        $payment = Settlement::firstOrFail();

        $this->actingAs($leader)->post(route('trips.settlements.reject', [$trip, $payment]))->assertRedirect();
        $this->assertModelMissing($payment);
    }

    public function test_the_receiver_or_leader_writing_it_down_needs_no_confirmation(): void
    {
        [$trip, $leader, $asha, $bilal] = $this->trip();

        $this->actingAs($leader)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $this->member($trip, $bilal)->id, 'to_member_id' => $this->member($trip, $leader)->id,
            'amount' => 300, 'method' => 'cash', 'settled_on' => now()->toDateString(),
        ]);

        $this->assertFalse(Settlement::firstOrFail()->isPending());
    }

    /* ------------------------------------------------------------------
       SMS wallet
    ------------------------------------------------------------------ */

    public function test_texts_are_counted_in_sms_parts(): void
    {
        $this->assertSame(1, SmsWallet::parts(str_repeat('a', 160)));
        $this->assertSame(2, SmsWallet::parts(str_repeat('a', 161)));
        $this->assertSame(2, SmsWallet::parts(str_repeat('a', 159).'{'));   // { counts twice
        $this->assertSame(1, SmsWallet::parts(str_repeat('ক', 70)));        // Bangla: 70 a part
        $this->assertSame(2, SmsWallet::parts(str_repeat('ক', 71)));
        $this->assertSame(0.9, app(SmsWallet::class)->cost(str_repeat('a', 200)));
    }

    public function test_a_text_is_charged_to_the_sender_and_refunded_if_it_fails(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sms_balance' => 1.00])->save();
        $wallet = app(SmsWallet::class);

        $wallet->send($user, '01711111111', 'Hello', 'Test');
        $this->assertSame(0.55, (float) $user->refresh()->sms_balance);

        // The gateway refuses: the money comes back.
        $this->gatewayError = 417;
        try {
            $wallet->send($user, '01711111111', 'Hello', 'Test');
            $this->fail('A refused text should throw.');
        } catch (SmsWalletException) {
        }
        $this->assertSame(0.55, (float) $user->refresh()->sms_balance);
        $this->assertSame(['charge', 'charge', 'refund'], SmsTransaction::orderBy('id')->pluck('type')->all());

        // Not enough left: nothing is sent or charged.
        $user->forceFill(['sms_balance' => 0.40])->save();
        $this->gatewayError = 0;
        $this->expectException(SmsWalletException::class);
        $wallet->send($user, '01711111111', 'Hello', 'Test');
    }

    /* ------------------------------------------------------------------
       Reminders
    ------------------------------------------------------------------ */

    public function test_the_person_owed_can_remind_and_choose_to_text(): void
    {
        Notification::fake();
        [$trip, $leader, $asha, $bilal] = $this->trip();
        $leader->forceFill(['sms_balance' => 5])->save();

        $this->actingAs($leader)->post(route('trips.remind', [$trip, $this->member($trip, $asha)]), ['sms' => '1'])
            ->assertSessionHas('status');

        Notification::assertSentTo($asha, TripNotification::class);
        Notification::assertSentTo($asha, PaymentDueReminder::class, fn ($n) => str_contains($n->payUrl, '/p/'));
        Http::assertSent(fn ($request) => $request['to'] === '8801711111111' && str_contains($request['msg'], 'Tk 300') && str_contains($request['msg'], '/p/'));
        $this->assertSame(4.55, (float) $leader->refresh()->sms_balance);

        $reminder = PaymentReminder::firstOrFail();
        $this->assertSame('app,email,sms', $reminder->channels);

        // Not twice in a day.
        $this->post(route('trips.remind', [$trip, $this->member($trip, $asha)]))->assertSessionHas('error');

        // Bilal is owed nothing by Asha, so he cannot chase her.
        $this->actingAs($bilal)->post(route('trips.remind', [$trip, $this->member($trip, $asha)]))->assertForbidden();
    }

    public function test_the_reminder_text_fits_one_plain_sms(): void
    {
        [$trip, , $asha] = $this->trip();
        $member = $this->member($trip, $asha);
        $service = app(\App\Services\PaymentReminders::class);

        $text = $service->text($trip, $member, 300, $service->owes($trip, $member), $member->payUrl());

        $this->assertSame(1, SmsWallet::parts($text));
        $this->assertStringNotContainsString('৳', $text);
    }

    public function test_reminders_go_out_automatically_after_the_trip_ends(): void
    {
        Notification::fake();
        [$trip, $leader] = $this->trip();
        $trip->forceFill(['status' => 'ended', 'ended_at' => now()->subDays(3)])->save();

        $this->artisan('sofor:remind-dues')->expectsOutputToContain('Sent 2 reminders')->assertSuccessful();

        // The same day again: nothing new.
        $this->artisan('sofor:remind-dues')->expectsOutputToContain('Sent 0 reminders');

        // A week on, the next round; texts only if the leader switched them on.
        $this->travel(8)->days();
        $this->artisan('sofor:remind-dues')->expectsOutputToContain('Sent 2 reminders');
        Http::assertNothingSent();

        $this->assertSame(4, PaymentReminder::where('automatic', true)->count());
    }

    /* ------------------------------------------------------------------
       Pay links
    ------------------------------------------------------------------ */

    public function test_a_pay_link_works_without_an_account(): void
    {
        Notification::fake();
        [$trip, $leader, $asha] = $this->trip();
        $this->actingAs($leader)->post(route('payout-methods.store'), ['provider' => 'bkash', 'account_number' => '01999888777']);
        $link = $this->member($trip, $asha)->payUrl();
        auth()->logout();

        $this->get($link)->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('Hi Asha')
            ->assertSee('01999-888777')
            ->assertSee('I paid Rifat');

        $this->post($link.'/paid', [
            'to_member_id' => $this->member($trip, $leader)->id, 'amount' => 300, 'method' => 'bkash', 'reference' => 'TRX1',
        ])->assertSessionHas('status');

        $payment = Settlement::firstOrFail();
        $this->assertTrue($payment->isPending());
        $this->assertNull($payment->recorded_by);
        Notification::assertSentTo($leader, TripNotification::class);

        $this->get($link)->assertSee('Waiting for confirmation');
        $this->get(route('pay.show', 'AAAAAAAAAAAA'))->assertNotFound();
    }
}
