<?php

namespace Tests\Feature;

use App\Models\SmsTopup;
use App\Models\SmsTransaction;
use App\Models\User;
use App\Services\BkashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresBkash;
use Tests\TestCase;

/**
 * bKash Checkout through Sofor's own merchant account. Trip dues no longer go
 * through it - travellers pay each other directly - so it is used to pay
 * Sofor: SMS wallet top-ups (here) and donations (SupportDonationTest).
 */
class BkashPaymentTest extends TestCase
{
    use ConfiguresBkash;
    use RefreshDatabase;

    /** What the fake gateway confirms it took; the first matching stub always wins, so it reads this. */
    private string $confirmedAmount = '250.00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBkash('tokenized');

        Cache::flush();
    }

    private function fakeGateway(): void
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token-123', 'statusCode' => '0000'], 200),
            '*/checkout/create' => Http::response([
                'paymentID' => 'TR0011',
                'bkashURL' => 'https://sandbox.bka.sh/checkout/TR0011',
                'statusCode' => '0000',
            ], 200),
            '*/checkout/execute*' => fn () => Http::response([
                'paymentID' => 'TR0011',
                'trxID' => 'BKA9X8Y7',
                'transactionStatus' => 'Completed',
                'amount' => $this->confirmedAmount,
                'statusCode' => '0000',
            ], 200),
        ]);
    }

    public function test_the_mfs_charge_is_added_on_top_of_the_share(): void
    {
        $service = BkashService::fromConfig();

        $breakdown = $service->breakdown(1000);

        $this->assertSame(1000.0, $breakdown['base']);
        $this->assertSame(1.5, $breakdown['percent']);
        $this->assertSame(15.0, $breakdown['charge']);
        $this->assertSame(1015.0, $breakdown['total']);

        // The sandbox only settles in whole taka, so it rounds up.
        config()->set('bkash.sandbox', true);
        $sandbox = BkashService::fromConfig()->breakdown(500);

        $this->assertSame(500.0, $sandbox['base']);
        $this->assertSame(8.0, $sandbox['charge']);
        $this->assertSame(508.0, $sandbox['total']);
    }

    public function test_a_traveller_tops_up_the_sms_wallet_through_bkash_checkout(): void
    {
        $user = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($user)->get(route('wallet.index'))->assertOk()->assertSee('Top up')->assertSee('৳0.45', false);

        $this->post(route('wallet.topup'), ['amount' => 250])
            ->assertRedirect('https://sandbox.bka.sh/checkout/TR0011');

        // The traveller is asked for exactly the top-up, nothing on top.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/create')
            && $request['amount'] === '250.00'
            && str_starts_with($request['merchantInvoiceNumber'], 'SMS-'));

        $this->get(route('wallet.callback').'?paymentID=TR0011&status=success')
            ->assertRedirect(route('wallet.index'))
            ->assertSessionHas('status');

        $this->assertSame(250.0, (float) $user->refresh()->sms_balance);

        $topup = SmsTopup::firstOrFail();
        $this->assertSame('completed', $topup->status);
        $this->assertSame('BKA9X8Y7', $topup->trx_id);

        $entry = SmsTransaction::firstOrFail();
        $this->assertSame('topup', $entry->type);
        $this->assertSame(250.0, (float) $entry->balance_after);
    }

    public function test_a_cancelled_top_up_adds_nothing(): void
    {
        $user = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($user)->post(route('wallet.topup'), ['amount' => 50]);
        $this->get(route('wallet.callback').'?paymentID=TR0011&status=cancel')
            ->assertRedirect(route('wallet.index'))
            ->assertSessionHas('error');

        $this->assertSame('cancelled', SmsTopup::firstOrFail()->status);
        $this->assertSame(0.0, (float) $user->refresh()->sms_balance);
    }

    public function test_the_same_bkash_payment_is_never_credited_twice(): void
    {
        $user = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($user)->post(route('wallet.topup'), ['amount' => 250]);
        $this->get(route('wallet.callback').'?paymentID=TR0011&status=success');

        // bKash (or the browser) repeats the callback.
        $this->get(route('wallet.callback').'?paymentID=TR0011&status=success')->assertSessionHas('status');

        $this->assertSame(250.0, (float) $user->refresh()->sms_balance);
        $this->assertSame(1, SmsTransaction::count());
    }

    public function test_nobody_can_finish_somebody_elses_top_up(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($owner)->post(route('wallet.topup'), ['amount' => 250]);

        $this->actingAs($other)->get(route('wallet.callback').'?paymentID=TR0011&status=success')
            ->assertSessionHas('error');

        $this->assertSame(0.0, (float) $owner->refresh()->sms_balance);
        $this->assertSame(0.0, (float) $other->refresh()->sms_balance);
        $this->assertSame('pending', SmsTopup::firstOrFail()->status);
    }

    public function test_a_different_confirmed_amount_adds_nothing(): void
    {
        $user = User::factory()->create();
        $this->confirmedAmount = '1.00';
        $this->fakeGateway();

        $this->actingAs($user)->post(route('wallet.topup'), ['amount' => 250]);
        $this->get(route('wallet.callback').'?paymentID=TR0011&status=success')->assertSessionHas('error');

        $this->assertSame(0.0, (float) $user->refresh()->sms_balance);
        $this->assertSame('failed', SmsTopup::firstOrFail()->status);
    }

    public function test_top_ups_stay_within_the_limits(): void
    {
        $user = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($user)->post(route('wallet.topup'), ['amount' => 9])->assertSessionHasErrors('amount');
        $this->post(route('wallet.topup'), ['amount' => 10.5])->assertSessionHasErrors('amount');
        $this->post(route('wallet.topup'), ['amount' => 50000])->assertSessionHasErrors('amount');
        $this->post(route('wallet.topup'), ['amount' => 10])->assertRedirect('https://sandbox.bka.sh/checkout/TR0011');

        $this->assertSame(1, SmsTopup::count());
    }

    public function test_top_ups_pause_when_bkash_is_not_configured(): void
    {
        config()->set('bkash.app_key', null);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('wallet.index'))->assertOk()->assertSee('top-ups are paused');
        $this->post(route('wallet.topup'), ['amount' => 50])->assertSessionHas('error');

        $this->assertSame(0, SmsTopup::count());
    }
}
