<?php

namespace Tests\Feature;

use App\Exceptions\BkashException;
use App\Models\Donation;
use App\Models\SmsTopup;
use App\Models\User;
use App\Services\BkashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresBkash;
use Tests\TestCase;

/**
 * The original (non tokenized) Checkout API answers on
 * `{host}/checkout/token/grant`, `/checkout/payment/create` and
 * `/checkout/payment/execute/{paymentID}`. Live merchant accounts are still
 * issued on it, so the flow has to work there too.
 */
class BkashLegacyApiTest extends TestCase
{
    use ConfiguresBkash;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBkash('legacy');

        Cache::flush();
    }

    private function fakeGateway(bool $includeCheckoutUrl = true): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response(['id_token' => 'legacy-token', 'statusCode' => '0000'], 200),
            '*/checkout/payment/create' => Http::response(array_filter([
                'paymentID' => 'TRL0001',
                // The real legacy API omits this field entirely.
                'bkashURL' => $includeCheckoutUrl ? 'https://checkout.pay.bka.sh/payment/TRL0001' : null,
                'statusCode' => '0000',
            ]), 200),
            '*/checkout/payment/execute/*' => Http::response([
                'paymentID' => 'TRL0001',
                'trxID' => 'BKA1234LEGACY',
                'transactionStatus' => 'Completed',
                'statusCode' => '0000',
            ], 200),
        ]);
    }

    public function test_the_api_generation_is_detected_from_the_host(): void
    {
        $this->assertSame('legacy', BkashService::detectGeneration('https://checkout.pay.bka.sh/v1.2.0-beta'));
        $this->assertSame('legacy', BkashService::detectGeneration('https://checkout.sandbox.bka.sh/v1.2.0-beta'));
        $this->assertSame('tokenized', BkashService::detectGeneration('https://tokenized.pay.bka.sh/v1.2.0-beta'));
        $this->assertSame('tokenized', BkashService::detectGeneration('https://tokenized.sandbox.bka.sh/v1.2.0-beta'));

        // An explicit value always wins over detection.
        $this->assertSame('tokenized', BkashService::detectGeneration('https://checkout.pay.bka.sh/v1.2.0-beta', 'tokenized'));
        $this->assertSame('legacy', BkashService::detectGeneration('https://tokenized.pay.bka.sh/v1.2.0-beta', 'legacy'));

        $this->assertSame('/payment/create', BkashService::pathsFor('legacy')['create']);
        $this->assertSame('/payment/execute/', BkashService::pathsFor('legacy')['execute']);
        $this->assertSame('/create', BkashService::pathsFor('tokenized')['create']);
        $this->assertSame('/execute', BkashService::pathsFor('tokenized')['execute']);
    }

    public function test_the_configured_generation_matches_the_host(): void
    {
        $this->assertSame('legacy', config('bkash.api'));
        $this->assertSame('/checkout', config('bkash.path_prefix'));
        $this->assertTrue(config('bkash.id_in_path'));
        $this->assertSame('/payment/create', config('bkash.paths')['create']);
    }

    public function test_an_sms_top_up_goes_through_the_legacy_gateway(): void
    {
        // The legacy API needs somebody to render the payment page for it.
        config()->set('bkash.mode', 'redirect');
        config()->set('bkash.checkout_url', 'https://checkout.pay.bka.sh/checkout/pay');

        $traveler = User::factory()->create(['name' => 'Nusrat']);
        $this->fakeGateway();

        $this->actingAs($traveler)->post(route('wallet.topup'), ['amount' => 200])
            ->assertRedirect('https://checkout.pay.bka.sh/payment/TRL0001');

        $callback = $this->actingAs($traveler)->get(route('wallet.callback').'?paymentID=TRL0001&status=success');

        $this->assertNull(session('error'), 'callback flashed: '.json_encode(session('error')));
        $callback->assertRedirect(route('wallet.index'));

        // Every call landed on the legacy paths, with the token header attached.
        $urls = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();

        $this->assertTrue(in_array('https://checkout.pay.bka.sh/v1.2.0-beta/checkout/token/grant', $urls), json_encode($urls));
        $this->assertTrue(in_array('https://checkout.pay.bka.sh/v1.2.0-beta/checkout/payment/create', $urls), json_encode($urls));
        $this->assertTrue(in_array('https://checkout.pay.bka.sh/v1.2.0-beta/checkout/payment/execute/TRL0001', $urls), json_encode($urls));

        // The traveller is asked for exactly the top-up; bKash's fee is Sofor's.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/checkout/payment/create')
            && $request['amount'] === '200.00'
            && $request->hasHeader('X-APP-Key', 'test-app-key'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/checkout/payment/execute/TRL0001')
            && $request->hasHeader('Authorization', 'legacy-token'));

        $this->assertSame(200.0, (float) $traveler->refresh()->sms_balance);
        $this->assertSame('BKA1234LEGACY', SmsTopup::firstOrFail()->trx_id);
    }

    public function test_a_gift_can_be_paid_through_the_legacy_gateway(): void
    {
        $this->fakeGateway();

        $this->post(route('support.start'), ['amount' => 250, 'name' => 'Rakib'])
            ->assertRedirect('https://checkout.pay.bka.sh/payment/TRL0001');

        $this->get(route('support.callback').'?paymentID=TRL0001&status=success')
            ->assertRedirect(route('support.thanks'));

        $donation = Donation::firstOrFail();

        $this->assertSame('completed', $donation->status);
        $this->assertSame(250.0, (float) $donation->amount);
        // The fee is on Sofor's side, so the donor was billed the gift alone.
        $this->assertSame(3.75, (float) $donation->charge);
        $this->assertSame(250.0, (float) $donation->total);
        $this->assertSame('BKA1234LEGACY', $donation->trx_id);
    }

    public function test_blank_credentials_count_as_not_configured(): void
    {
        // A half filled .env used to render a bKash button that always failed.
        config()->set('bkash.app_secret', '');

        $this->get(route('support.index'))->assertOk()->assertSee('Online support is paused');
    }

    public function test_an_ownerless_payment_id_is_never_accepted(): void
    {
        $this->fakeGateway();

        $this->get(route('support.callback').'?paymentID=TRL0001&status=success')
            ->assertRedirect(route('support.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Donation::count());
    }

    public function test_a_legacy_account_uses_the_checkout_url_it_was_given(): void
    {
        // Only a redirect flow needs a page URL; the browser flow renders its own.
        $this->configureBkash('legacy', false, 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout/pay');
        config()->set('bkash.mode', 'redirect');
        $this->fakeGateway(includeCheckoutUrl: false);

        // No bkashURL comes back from the gateway, so the configured page is used
        // with the payment id appended.
        $this->post(route('support.start'), ['amount' => 100])
            ->assertRedirect('https://checkout.pay.bka.sh/v1.2.0-beta/checkout/pay?paymentID=TRL0001');

        $this->assertSame('pending', Donation::firstOrFail()->status);
    }

    public function test_a_redirect_flow_without_a_checkout_url_cannot_take_payments(): void
    {
        $this->configureBkash('legacy', false, '');
        config()->set('bkash.mode', 'redirect');
        $this->fakeGateway(includeCheckoutUrl: false);

        $service = BkashService::fromConfig();

        // Credentials are fine, but there is nowhere to send the traveller.
        $this->assertTrue($service->isConfigured());
        $this->assertFalse($service->canTakePayments());

        $this->get(route('support.index'))->assertOk()->assertSee('Online support is paused');

        $this->post(route('support.start'), ['amount' => 100])->assertSessionHas('error');

        $this->assertSame(0, Donation::count());

        // The service says exactly what to set if it is called directly.
        try {
            $service->createPayment(100, 'SOFOR-TEST-1', 'https://sofor.test/callback');
            $this->fail('A payment without a checkout page must not succeed.');
        } catch (BkashException $exception) {
            $this->assertStringContainsString('BKASH_CHECKOUT_URL', $exception->getMessage());
        }
    }
}
