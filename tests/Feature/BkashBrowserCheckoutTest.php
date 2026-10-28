<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\SmsTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;
use Tests\Concerns\ConfiguresBkash;
use Tests\TestCase;

/**
 * Legacy bKash accounts do not get a payment page URL with the payment: bKash's
 * Checkout JS renders it in the page, so the traveller never leaves Sofor. The
 * browser calls two JSON endpoints instead:
 *
 *   POST .../bkash/create   -> Sofor calls bKash Create Payment
 *   POST .../bkash/execute  -> Sofor calls bKash Execute Payment and records it
 */
class BkashBrowserCheckoutTest extends TestCase
{
    use ConfiguresBkash;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBkash('legacy');
        config()->set('bkash.mode', 'browser');

        Cache::flush();
    }

    private function fakeGateway(): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response(['id_token' => 'legacy-token', 'statusCode' => '0000'], 200),
            '*/checkout/payment/create' => Http::response([
                // Exactly what the real legacy API answers: no payment page URL.
                'paymentID' => 'CH0011BROWSER',
                'transactionStatus' => 'Initiated',
                'amount' => '101.50',
                'statusCode' => '0000',
            ], 200),
            '*/checkout/payment/execute/*' => Http::response([
                'paymentID' => 'CH0011BROWSER',
                'trxID' => 'BKA778899',
                'transactionStatus' => 'Completed',
                'statusCode' => '0000',
            ], 200),
        ]);
    }

    public function test_the_browser_flow_needs_no_checkout_url(): void
    {
        $service = \App\Services\BkashService::fromConfig();

        $this->assertTrue($service->usesBrowserCheckout());
        $this->assertNull(config('bkash.checkout_url'));
        $this->assertTrue($service->canTakePayments());
    }

    public function test_the_support_page_drives_the_browser_flow(): void
    {
        $this->fakeGateway();

        // The page hands the two Sofor endpoints to bKash's Checkout JS. They are
        // embedded with @js(), so compare against the very same encoder.
        $this->get(route('support.index'))
            ->assertOk()
            ->assertSee('SoforBkash', false)
            ->assertSee(Js::from(route('support.bkash.create'))->toHtml(), false)
            ->assertSee(Js::from(route('support.bkash.execute'))->toHtml(), false)
            ->assertSee('Opening bKash');
    }

    public function test_a_gift_is_created_and_executed_from_the_browser(): void
    {
        $this->fakeGateway();

        $create = $this->postJson(route('support.bkash.create'), [
            'amount' => 100,
            'name' => 'Rakib',
            'message' => 'Keep it up',
        ]);

        $create->assertOk()
            ->assertJson([
                'ok' => true,
                'paymentID' => 'CH0011BROWSER',
                'base' => 100.0,
                'charge' => 1.5,
                'total' => 100.0,
            ]);

        $donation = Donation::firstOrFail();

        $this->assertSame('pending', $donation->status);
        $this->assertSame('CH0011BROWSER', $donation->payment_id);
        $this->assertSame('Rakib', $donation->name);

        // bKash is asked for the gift only - Sofor absorbs the MFS fee.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/payment/create')
            && $request['amount'] === '100.00');

        $execute = $this->postJson(route('support.bkash.execute'), ['paymentID' => 'CH0011BROWSER']);

        $execute->assertOk()->assertJson([
            'ok' => true,
            'redirect' => route('support.thanks'),
        ]);

        $donation->refresh();

        $this->assertSame('completed', $donation->status);
        $this->assertSame('BKA778899', $donation->trx_id);
        $this->assertSame(100.0, (float) $donation->amount);
        $this->assertSame(100.0, (float) $donation->total);
    }

    public function test_the_wallet_takes_the_amount_the_way_the_bkash_script_sends_it(): void
    {
        $user = User::factory()->create(['locale' => 'bn']);
        $this->fakeGateway();

        // bKash's checkout script hands back "100.00"; a Bangla keyboard types "১০০".
        $this->actingAs($user)->postJson(route('wallet.bkash.create'), ['amount' => '100.00'])->assertOk()->assertJson(['ok' => true, 'base' => 100.0]);
        $this->actingAs($user)->postJson(route('wallet.bkash.create'), ['amount' => '১০০'])->assertOk()->assertJson(['ok' => true, 'base' => 100.0]);

        $this->actingAs($user)->postJson(route('wallet.bkash.create'), ['amount' => '100.50'])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'message' => 'পূর্ণ টাকায় টপ-আপ করুন, পয়সা ছাড়া.']);
    }

    public function test_an_sms_top_up_is_created_and_executed_from_the_browser(): void
    {
        $user = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($user)->postJson(route('wallet.bkash.create'), ['amount' => 100])
            ->assertOk()
            ->assertJson(['ok' => true, 'paymentID' => 'CH0011BROWSER', 'base' => 100.0, 'total' => 100.0]);

        $this->actingAs($user)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0011BROWSER'])
            ->assertOk()
            ->assertJson(['ok' => true, 'redirect' => route('wallet.index')]);

        $this->assertSame(100.0, (float) $user->refresh()->sms_balance);
        $topup = SmsTopup::firstOrFail();
        $this->assertSame('completed', $topup->status);
        $this->assertSame('BKA778899', $topup->trx_id);

        // A repeated execute (double click, bKash retry) never credits twice.
        $this->actingAs($user)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0011BROWSER'])->assertOk();
        $this->assertSame(100.0, (float) $user->refresh()->sms_balance);
    }

    public function test_executing_the_same_payment_twice_records_it_once(): void
    {
        $this->fakeGateway();

        $this->postJson(route('support.bkash.create'), ['amount' => 100])->assertOk();
        $this->postJson(route('support.bkash.execute'), ['paymentID' => 'CH0011BROWSER'])->assertOk();

        // bKash (or a double click) repeats the execute call.
        $this->postJson(route('support.bkash.execute'), ['paymentID' => 'CH0011BROWSER'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, Donation::query()->completed()->count());
    }

    public function test_a_payment_id_that_was_never_created_is_refused(): void
    {
        $this->fakeGateway();

        $this->postJson(route('support.bkash.execute'), ['paymentID' => 'CH0011UNKNOWN'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Donation::count());
    }

    public function test_the_browser_endpoints_validate_their_input(): void
    {
        $this->fakeGateway();

        $this->postJson(route('support.bkash.create'), ['amount' => 5])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->postJson(route('support.bkash.execute'), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Donation::count());
    }

    public function test_one_traveller_cannot_finish_another_travellers_top_up(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->fakeGateway();

        $this->actingAs($owner)->postJson(route('wallet.bkash.create'), ['amount' => 100])->assertOk();

        $this->actingAs($other)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0011BROWSER'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0.0, (float) $owner->refresh()->sms_balance);
        $this->assertSame(0.0, (float) $other->refresh()->sms_balance);
    }

    public function test_a_failed_create_reports_the_gateway_problem(): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response(['status' => 'fail', 'msg' => 'Invalid or unrecognized access credentials.'], 200),
        ]);

        $this->postJson(route('support.bkash.create'), ['amount' => 100])
            ->assertStatus(502)
            ->assertJson(['ok' => false]);

        // The attempt is kept so the admin can see it never completed.
        $this->assertSame('failed', Donation::firstOrFail()->status);
    }
}
