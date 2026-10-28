<?php

namespace Tests\Feature;

use App\Models\SmsTopup;
use App\Models\User;
use App\Services\BkashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresBkash;
use Tests\TestCase;

/**
 * bKash as it really answers. A paid payment is transactionStatus "Completed"
 * (there is no "Success" - Sofor used to wait for one, so a real ৳10 top-up
 * was taken by bKash and shown as failed). When execute is unclear, Query
 * Payment decides; and `bkash:reconcile` books what slipped through.
 */
class BkashRealResponsesTest extends TestCase
{
    use ConfiguresBkash;
    use RefreshDatabase;

    private const QUERY_URL = 'https://checkout.pay.bka.sh/v1.2.0-beta/checkout/payment/query/CH0010TEN';

    protected function setUp(): void
    {
        parent::setUp();

        // Sofor's live account: the legacy API, through bKash's Checkout JS.
        $this->configureBkash('legacy');
        Cache::flush();
    }

    /** @param  array<string, mixed>|null  $execute  null = bKash answers with an error */
    private function gateway(?array $execute, array $query = []): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response(['id_token' => 'legacy-token', 'statusCode' => '0000'], 200),
            '*/checkout/payment/create' => Http::response(['paymentID' => 'CH0010TEN', 'transactionStatus' => 'Initiated', 'amount' => '10.00', 'intent' => 'sale', 'currency' => 'BDT'], 200),
            '*/checkout/payment/execute/*' => $execute !== null
                ? Http::response($execute, 200)
                : Http::response(['errorCode' => '2117', 'errorMessage' => 'Payment execution already been called before'], 200),
            '*/checkout/payment/query/*' => Http::response($query, 200),
        ]);
    }

    private function startTopUp(User $user): void
    {
        $this->actingAs($user)->postJson(route('wallet.bkash.create'), ['amount' => '10.00'])->assertOk()->assertJson(['paymentID' => 'CH0010TEN']);
    }

    public function test_a_completed_ten_taka_top_up_lands_in_the_wallet(): void
    {
        $user = User::factory()->create();
        $this->gateway(['paymentID' => 'CH0010TEN', 'createTime' => '2026-10-26T19:40:00:000 GMT+0600', 'updateTime' => '2026-10-26T19:41:05:000 GMT+0600',
            'trxID' => 'BKA10TAKA1', 'transactionStatus' => 'Completed', 'amount' => '10.00', 'currency' => 'BDT', 'intent' => 'sale', 'merchantInvoiceNumber' => 'SMS-X']);

        $this->startTopUp($user);
        $this->actingAs($user)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0010TEN'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(10.0, (float) $user->refresh()->sms_balance);
        $this->assertSame(['completed', 'BKA10TAKA1'], [SmsTopup::firstOrFail()->status, SmsTopup::firstOrFail()->trx_id]);
    }

    public function test_an_unclear_execute_asks_bkash_before_calling_it_failed(): void
    {
        $user = User::factory()->create();
        // Execute errors ("already executed", say), but the money did move.
        $this->gateway(null, ['paymentID' => 'CH0010TEN', 'trxID' => 'BKA10TAKA2', 'transactionStatus' => 'Completed', 'amount' => '10.00']);

        $this->startTopUp($user);
        $this->actingAs($user)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0010TEN'])->assertOk();

        $this->assertSame(10.0, (float) $user->refresh()->sms_balance);
        // Legacy Query Payment is GET /checkout/payment/query/{paymentID}.
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === self::QUERY_URL);
    }

    public function test_a_payment_bkash_never_completed_adds_nothing(): void
    {
        $user = User::factory()->create();
        $this->gateway(null, ['paymentID' => 'CH0010TEN', 'transactionStatus' => 'Initiated', 'amount' => '10.00']);

        $this->startTopUp($user);
        $this->actingAs($user)->postJson(route('wallet.bkash.execute'), ['paymentID' => 'CH0010TEN'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0.0, (float) $user->refresh()->sms_balance);
        $this->assertSame('failed', SmsTopup::firstOrFail()->status);
    }

    public function test_reconcile_books_a_paid_top_up_that_was_marked_failed_once(): void
    {
        $user = User::factory()->create();
        $topup = new SmsTopup(['amount' => 10, 'invoice' => 'SMS-LOST10', 'gateway' => 'bkash', 'payment_id' => 'CH0010TEN', 'status' => SmsTopup::STATUS_FAILED]);
        $topup->user()->associate($user);
        $topup->save();
        $topup->forceFill(['created_at' => now()->subHours(20)])->save();

        $this->gateway([], ['paymentID' => 'CH0010TEN', 'trxID' => 'BKA10TAKA3', 'transactionStatus' => 'Completed', 'amount' => '10.00']);

        // A dry run only reports.
        $this->artisan('bkash:reconcile', ['--dry-run' => true])->expectsOutputToContain('would book')->assertSuccessful();
        $this->assertSame(0.0, (float) $user->refresh()->sms_balance);

        $this->artisan('bkash:reconcile')->expectsOutputToContain('Booked 1.')->assertSuccessful();
        $this->assertSame(10.0, (float) $user->refresh()->sms_balance);
        $this->assertSame(['completed', 'BKA10TAKA3'], [$topup->refresh()->status, $topup->trx_id]);

        // Again, and the hourly run after it: nothing is credited twice.
        $this->artisan('bkash:reconcile')->expectsOutputToContain('Nothing failed or pending')->assertSuccessful();
        $this->assertSame(10.0, (float) $user->refresh()->sms_balance);
    }

    public function test_tokenized_execute_and_query_send_the_payment_id_in_the_body(): void
    {
        $this->configureBkash('tokenized');
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token', 'statusCode' => '0000'], 200),
            '*/checkout/execute' => Http::response(['statusCode' => '2117', 'statusMessage' => 'Payment execution already been called before'], 200),
            '*/checkout/payment/status' => Http::response(['statusCode' => '0000', 'paymentID' => 'TR0010', 'trxID' => 'BKA10TAKA4', 'transactionStatus' => 'Completed', 'amount' => '10.00'], 200),
        ]);

        $settled = BkashService::fromConfig()->settle('TR0010');

        $this->assertTrue($settled['completed']);
        $this->assertSame('BKA10TAKA4', $settled['result']['trxID']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/tokenized/checkout/execute') && $request['paymentID'] === 'TR0010');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/tokenized/checkout/payment/status') && $request['paymentID'] === 'TR0010');
    }

    public function test_only_completed_means_paid(): void
    {
        $this->assertTrue(BkashService::isCompleted(['transactionStatus' => 'Completed', 'statusCode' => '0000']));
        $this->assertTrue(BkashService::isCompleted(['transactionStatus' => 'Completed']));
        $this->assertFalse(BkashService::isCompleted(['transactionStatus' => 'Completed', 'statusCode' => '2062']));
        $this->assertFalse(BkashService::isCompleted(['transactionStatus' => 'Initiated']));
        $this->assertFalse(BkashService::isCompleted(['transactionStatus' => 'Authorized']));
        $this->assertFalse(BkashService::isCompleted(['transactionStatus' => 'Success']));
        $this->assertFalse(BkashService::isCompleted(['errorCode' => '2117']));
    }
}
