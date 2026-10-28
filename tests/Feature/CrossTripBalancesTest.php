<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\CrossTripBalances;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "You owe Asha 500 on Sajek, she owes you 300 on Sylhet": one balance per
 * friend over every trip, settled with a single payment.
 */
class CrossTripBalancesTest extends TestCase
{
    use RefreshDatabase;

    private User $rifat;

    private User $asha;

    private Trip $sajek;

    private Trip $sylhet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rifat = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);

        // Sajek: Asha paid 1,000 for both - Rifat owes her 500.
        $this->sajek = $this->trip('Sajek', $this->rifat, $this->asha, payer: $this->asha, amount: 1000);
        // Sylhet: Rifat paid 600 for both - Asha owes him 300.
        $this->sylhet = $this->trip('Sylhet', $this->asha, $this->rifat, payer: $this->rifat, amount: 600);
    }

    private function trip(string $title, User $leader, User $friend, User $payer, float $amount, string $currency = 'BDT'): Trip
    {
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => $title, 'currency' => $currency]);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->invite($trip, $leader, ['name' => $friend->name, 'email' => $friend->email]);

        $this->actingAs($payer)->post(route('trips.expenses.store', $trip), [
            'title' => 'Stay', 'amount' => $amount, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->where('user_id', $payer->id)->value('id'), 'split_type' => 'equal',
            'participant_ids' => $trip->members()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        return $trip;
    }

    private function settledEverywhere(): bool
    {
        return collect([$this->sajek, $this->sylhet])->every(fn (Trip $trip) => app(TripLedger::class)->summarize($trip)['suggestions'] === []);
    }

    public function test_balances_net_out_per_friend_across_trips(): void
    {
        $row = app(CrossTripBalances::class)->between($this->rifat, $this->asha, 'BDT');

        $this->assertSame(-200.0, $row['net']);
        $this->assertCount(2, $row['items']);

        $this->actingAs($this->rifat)->get(route('balances.index'))
            ->assertOk()
            ->assertSee('Asha Rahman')
            ->assertSee('৳200')
            ->assertSee('Settle up');

        $this->actingAs($this->asha)->get(route('balances.index'))->assertSee('Owed to you')->assertSee('Mark received');
    }

    public function test_one_payment_settles_every_trip_once_the_friend_confirms(): void
    {
        Notification::fake();

        $this->actingAs($this->rifat)->post(route('balances.settle', $this->asha), [
            'currency' => 'BDT', 'method' => 'bkash', 'reference' => 'TRX9',
        ])->assertSessionHas('status');

        $items = Settlement::orderBy('trip_id')->get();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items->pluck('batch')->unique()->count());
        $this->assertTrue($items->every->isPending());
        $this->assertSame(PaymentMethod::Bkash, $items->firstWhere('trip_id', $this->sajek->id)->methodEnum());
        $this->assertSame('TRX9', $items->firstWhere('trip_id', $this->sajek->id)->reference);
        $this->assertSame(PaymentMethod::Netted, $items->firstWhere('trip_id', $this->sylhet->id)->methodEnum());
        $this->assertTrue($this->settledEverywhere());

        // One notification for the whole thing.
        Notification::assertSentToTimes($this->asha, TripNotification::class, 1);

        // Rifat cannot confirm his own settle up, nor delete one part of it.
        $sajekItem = $items->firstWhere('trip_id', $this->sajek->id);
        $this->actingAs($this->rifat)->post(route('trips.settlements.confirm', [$this->sylhet, $items->firstWhere('trip_id', $this->sylhet->id)]))->assertForbidden();
        $this->assertFalse($this->asha->can('deleteSettlement', [$this->sajek, $sajekItem]));

        $this->actingAs($this->asha)->get(route('balances.index'))->assertSee('settled up with you across 2 trips');
        $this->post(route('trips.settlements.confirm', [$this->sajek, $sajekItem]))->assertRedirect();

        $this->assertSame(0, Settlement::whereNull('confirmed_at')->count());
        Notification::assertSentTo($this->rifat, TripNotification::class, fn ($n) => $n->title === 'Settled up with Asha Rahman');
    }

    public function test_not_received_takes_the_whole_settle_up_back_off(): void
    {
        $this->actingAs($this->rifat)->post(route('balances.settle', $this->asha), ['currency' => 'BDT', 'method' => 'nagad']);

        $this->actingAs($this->asha)->post(route('trips.settlements.reject', [$this->sylhet, Settlement::where('trip_id', $this->sylhet->id)->firstOrFail()]))->assertRedirect();

        $this->assertSame(0, Settlement::count());
        $this->assertSame(-200.0, app(CrossTripBalances::class)->between($this->rifat, $this->asha, 'BDT')['net']);
    }

    public function test_the_friend_who_is_owed_marks_it_received_and_it_counts_at_once(): void
    {
        $this->actingAs($this->asha)->post(route('balances.settle', $this->rifat), ['currency' => 'BDT', 'method' => 'cash'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'received'));

        $this->assertSame(0, Settlement::whereNull('confirmed_at')->count());
        $this->assertTrue($this->settledEverywhere());
        $this->assertNull(app(CrossTripBalances::class)->between($this->rifat, $this->asha, 'BDT'));
    }

    public function test_currencies_never_net_against_each_other(): void
    {
        $this->trip('Bangkok', $this->rifat, $this->asha, payer: $this->asha, amount: 100, currency: 'THB');

        $rows = app(CrossTripBalances::class)->forUser($this->rifat);

        $this->assertSame(['BDT' => -200.0, 'THB' => -50.0], $rows->pluck('net', 'currency')->all());
    }

    public function test_nobody_reports_a_netted_payment_by_hand(): void
    {
        $this->actingAs($this->rifat)->post(route('trips.settlements.store', $this->sajek), [
            'from_member_id' => $this->sajek->members()->where('user_id', $this->rifat->id)->value('id'),
            'amount' => 500, 'method' => 'netted', 'settled_on' => now()->toDateString(),
        ])->assertSessionHasErrors('method');
    }
}
