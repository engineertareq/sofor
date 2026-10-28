<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresBkash;
use Tests\TestCase;

class SupportDonationTest extends TestCase
{
    use ConfiguresBkash;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBkash('tokenized');
    }

    private function fakeGateway(string $status = 'Completed'): void
    {
        Http::fake([
            '*/token/grant' => Http::response(['id_token' => 'token-123', 'statusCode' => '0000'], 200),
            '*/checkout/create' => Http::response([
                'paymentID' => 'TR0099',
                'bkashURL' => 'https://sandbox.bka.sh/checkout/TR0099',
                'statusCode' => '0000',
            ], 200),
            '*/checkout/execute*' => Http::response([
                'paymentID' => 'TR0099',
                'trxID' => 'BKA7788',
                'transactionStatus' => $status,
                'amount' => '500.00',   // what Sofor asked for: the gift, nothing on top
                'statusCode' => '0000',
            ], 200),
        ]);
    }

    public function test_anybody_can_open_the_support_page(): void
    {
        $this->get(route('support.index'))
            ->assertOk()
            ->assertSee('Support Sofor')
            ->assertSee('Where your money goes')
            // The donor is told the fee is on Sofor's side before they pay.
            ->assertSee('comes out of')
            ->assertSee('1.5%', false);
    }

    public function test_the_support_page_shows_no_supporter_data_until_a_gift_is_recorded(): void
    {
        $this->get(route('support.index'))
            ->assertOk()
            ->assertDontSee('Thank you, supporters')
            ->assertDontSee('supporter so far');

        Donation::create([
            'name' => 'Rakib',
            'amount' => 500,
            'charge' => 7.5,
            'total' => 507.5,
            'percent' => 1.5,
            'invoice' => 'SUPPORT-TEST02',
            'status' => Donation::STATUS_COMPLETED,
            'trx_id' => 'BKA9900',
        ]);

        $this->get(route('support.index'))
            ->assertOk()
            ->assertSee('Thank you, supporters')
            ->assertSee('Rakib')
            ->assertSee('supporter so far');
    }

    public function test_a_gift_can_be_paid_through_bkash_checkout(): void
    {
        $this->fakeGateway();

        $start = $this->post(route('support.start'), [
            'amount' => 500,
            'name' => 'Rakib',
            'contact' => '01712345678',
            'message' => 'Great work',
        ]);

        $start->assertRedirect('https://sandbox.bka.sh/checkout/TR0099');

        // bKash is asked for the gift only; the MFS fee is Sofor's to carry.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/create')
            && $request['amount'] === '500.00'
            && $request['merchantInvoiceNumber'] === Donation::first()->invoice);

        $pending = Donation::firstOrFail();
        $this->assertSame('pending', $pending->status);
        $this->assertSame('TR0099', $pending->payment_id);
        $this->assertSame(500.0, (float) $pending->amount);
        $this->assertSame(7.5, (float) $pending->charge);
        $this->assertSame(500.0, (float) $pending->total);

        $callback = $this->get(route('support.callback').'?paymentID=TR0099&status=success');
        $callback->assertRedirect(route('support.thanks'));

        $donation = Donation::firstOrFail();
        $this->assertSame('completed', $donation->status);
        $this->assertSame('BKA7788', $donation->trx_id);

        // The record keeps the whole story: the gift, the fee Sofor carried and
        // the amount the donor's bKash was actually billed.
        $this->assertSame(500.0, (float) $donation->amount);
        $this->assertSame(7.5, (float) $donation->charge);
        $this->assertSame(500.0, (float) $donation->total);
        $this->assertSame('Rakib', $donation->name);

        $this->get(route('support.thanks'))->assertOk()->assertSee('BKA7788')->assertSee('500');
    }

    public function test_a_cancelled_gift_is_recorded_as_cancelled_and_counts_for_nothing(): void
    {
        $this->fakeGateway();

        $this->post(route('support.start'), ['amount' => 100])->assertRedirect();

        $this->get(route('support.callback').'?paymentID=TR0099&status=cancel')
            ->assertRedirect(route('support.index'));

        $donation = Donation::firstOrFail();
        $this->assertSame('cancelled', $donation->status);
        $this->assertNull($donation->trx_id);
        $this->assertSame(0, Donation::query()->completed()->count());
    }

    public function test_a_callback_without_a_started_gift_is_refused(): void
    {
        $this->fakeGateway();

        $this->get(route('support.callback').'?paymentID=TR0099&status=success')
            ->assertRedirect(route('support.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Donation::count());
    }

    public function test_a_gift_cannot_be_counted_twice(): void
    {
        $this->fakeGateway();

        $this->post(route('support.start'), ['amount' => 500])->assertRedirect();
        $this->get(route('support.callback').'?paymentID=TR0099&status=success')->assertRedirect(route('support.thanks'));

        // bKash (or the browser) repeats the callback: no second donation appears.
        $this->get(route('support.callback').'?paymentID=TR0099&status=success')
            ->assertRedirect(route('support.thanks'));

        $this->assertSame(1, Donation::query()->completed()->count());
    }

    public function test_a_failed_gateway_response_never_creates_a_completed_gift(): void
    {
        $this->fakeGateway('Failure');

        $this->post(route('support.start'), ['amount' => 250])->assertRedirect();

        $this->get(route('support.callback').'?paymentID=TR0099&status=success')
            ->assertRedirect(route('support.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Donation::query()->completed()->count());
    }

    public function test_the_amount_is_validated(): void
    {
        $this->post(route('support.start'), ['amount' => 5])->assertSessionHasErrors('amount');
        $this->post(route('support.start'), ['amount' => 500000])->assertSessionHasErrors('amount');
        $this->post(route('support.start'), [])->assertSessionHasErrors('amount');

        $this->assertSame(0, Donation::count());
    }

    public function test_the_donate_button_is_hidden_when_bkash_is_not_configured(): void
    {
        config()->set('bkash.app_key', null);
        config()->set('bkash.app_secret', null);

        $this->get(route('support.index'))->assertOk()->assertSee('Online support is paused');

        $this->post(route('support.start'), ['amount' => 500])
            ->assertSessionHas('error');

        $this->assertSame(0, Donation::count());
    }

    public function test_the_gateway_fee_is_never_added_to_what_the_donor_pays(): void
    {
        $this->fakeGateway();

        $this->post(route('support.start'), ['amount' => 250])->assertRedirect();

        $donation = Donation::firstOrFail();

        // The donor pays the gift they chose, full stop. bKash's 1.5% is a cost
        // Sofor carries, so it is recorded but never billed on top.
        $this->assertSame(250.0, (float) $donation->amount);
        $this->assertSame(250.0, (float) $donation->total);
        $this->assertSame(3.75, (float) $donation->charge);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/create')
            && $request['amount'] === '250.00');
    }

    public function test_an_administrator_sees_the_support_totals(): void
    {
        Donation::create([
            'name' => 'Rakib',
            'amount' => 500,
            // The fee is Sofor's cost, so the donor's total is the gift itself.
            'charge' => 7.5,
            'total' => 500,
            'percent' => 1.5,
            'invoice' => 'SUPPORT-TEST01',
            'status' => Donation::STATUS_COMPLETED,
            'trx_id' => 'BKA7788',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.donations.index'))
            ->assertOk()
            ->assertSee('Rakib')
            ->assertSee('BKA7788')
            ->assertSee('500')
            // The fee and the resulting net are both shown as Sofor's side.
            ->assertSee('bKash fee absorbed')
            ->assertSee('Net received')
            ->assertSee('492.5');
    }

    public function test_regular_travellers_cannot_open_the_support_totals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.donations.index'))->assertForbidden();
    }
}
