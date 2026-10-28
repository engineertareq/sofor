<?php

namespace Tests\Feature;

use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripCollection;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\TripLedger;
use App\Services\TripPot;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Advance collections: the leader asks everyone for an amount up front, one
 * person holds it and pays shared costs from it, and the leftover comes back
 * through the normal settle up.
 */
class TripPotTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $leader;

    private User $asha;

    private User $bilal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);
        $this->bilal = User::factory()->create(['name' => 'Bilal Khan']);
        $this->trip = Trip::factory()->create(['user_id' => $this->leader->id, 'title' => 'Sajek']);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->bilal->name, 'email' => $this->bilal->email]);
    }

    private function member(User $user): TripMember
    {
        return $this->trip->members()->where('user_id', $user->id)->firstOrFail();
    }

    private function collect(array $overrides = []): TripCollection
    {
        $this->actingAs($this->leader)->post(route('trips.collections.store', $this->trip), $overrides + [
            'title' => 'Bus and cottage', 'amount_per_person' => 1000, 'collector_member_id' => $this->member($this->leader)->id,
        ])->assertSessionHasNoErrors();

        return TripCollection::latest('id')->firstOrFail();
    }

    private function payIn(User $payer, TripCollection $collection, float $amount, ?int $to = null): void
    {
        $this->actingAs($payer)->post(route('trips.settlements.store', $this->trip), array_filter([
            'from_member_id' => $this->member($payer)->id, 'to_member_id' => $to, 'amount' => $amount, 'method' => 'bkash',
            'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
        ]))->assertSessionHasNoErrors();
    }

    public function test_the_leader_starts_a_collection_and_everyone_is_told(): void
    {
        Notification::fake();

        $this->actingAs($this->asha)->post(route('trips.collections.store', $this->trip), [
            'title' => 'Advance', 'amount_per_person' => 1000, 'collector_member_id' => $this->member($this->asha)->id,
        ])->assertForbidden();

        $collection = $this->collect(['due_on' => now()->addDays(3)->toDateString()]);

        Notification::assertSentTo($this->asha, TripNotification::class, fn ($n) => str_contains($n->title, 'Bus and cottage: ৳1,000 a person'));
        Notification::assertNotSentTo($this->leader, TripNotification::class);

        $this->actingAs($this->asha)->get(route('trips.show', $this->trip))
            ->assertOk()
            ->assertSee('Trip pot')
            ->assertSee('Bus and cottage: send ৳1,000 to Rifat Hasan')
            ->assertSee('I paid Rifat');

        $this->assertSame(1000.0, (float) $collection->amount_per_person);
    }

    public function test_advances_go_to_the_holder_and_wait_for_confirmation(): void
    {
        $collection = $this->collect();

        // Whatever "to" says, an advance goes to the holder.
        $this->payIn($this->asha, $collection, 1000, $this->member($this->bilal)->id);
        $advance = Settlement::firstOrFail();
        $this->assertSame($this->member($this->leader)->id, $advance->to_member_id);
        $this->assertSame($collection->id, $advance->collection_id);
        $this->assertTrue($advance->isPending());

        $pot = app(TripPot::class)->summaries($this->trip)->first();
        $this->assertSame(0.0, $pot['collected']);
        $this->assertSame(1000.0, $pot['pending']);

        $this->actingAs($this->leader)->post(route('trips.settlements.confirm', [$this->trip, $advance]));

        $pot = app(TripPot::class)->summaries($this->trip)->first();
        $this->assertSame(1000.0, $pot['collected']);
        $this->assertSame(2000.0, $pot['target']);
        $this->assertSame(1, $pot['paid_count']);
        $this->assertSame(['unpaid', 'paid'], $pot['rows']->pluck('status')->all());
    }

    public function test_the_leftover_in_the_pot_is_paid_back_in_the_settle_up(): void
    {
        $collection = $this->collect();
        $leaderMember = $this->member($this->leader);

        // The holder marks both advances paid, so they count at once.
        foreach ([$this->asha, $this->bilal] as $payer) {
            $this->actingAs($this->leader)->post(route('trips.settlements.store', $this->trip), [
                'from_member_id' => $this->member($payer)->id, 'amount' => 1000, 'method' => 'cash',
                'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
            ])->assertSessionHasNoErrors();
        }

        // 2,400 of costs paid from the pot, split three ways.
        $this->travel(1)->minutes();
        $this->post(route('trips.expenses.store', $this->trip), [
            'title' => 'Bus', 'amount' => 2400, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $leaderMember->id, 'split_type' => 'equal',
            'participant_ids' => $this->trip->members()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $pot = app(TripPot::class)->summaries($this->trip)->first();
        $this->assertSame(2000.0, $pot['collected']);
        $this->assertSame(2400.0, $pot['spent']);
        $this->assertSame(-400.0, $pot['in_pot']);

        // Each of them owes 800 and paid 1,000 in; the holder pays 200 back to each.
        $suggestions = collect(app(TripLedger::class)->summarize($this->trip)['suggestions']);
        $this->assertCount(2, $suggestions);
        $this->assertTrue($suggestions->every(fn ($s) => $s['from_id'] === $leaderMember->id && $s['amount'] === 200.0));
    }

    public function test_a_closed_collection_takes_no_more_advances_and_the_holder_cannot_pay_themselves(): void
    {
        $collection = $this->collect();

        $this->actingAs($this->leader)->post(route('trips.settlements.store', $this->trip), [
            'from_member_id' => $this->member($this->leader)->id, 'amount' => 1000, 'method' => 'cash',
            'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
        ])->assertSessionHasErrors();

        $this->post(route('trips.collections.toggle', [$this->trip, $collection]));
        $this->assertFalse($collection->refresh()->isOpen());

        $this->actingAs($this->asha)->post(route('trips.settlements.store', $this->trip), [
            'from_member_id' => $this->member($this->asha)->id, 'amount' => 1000, 'method' => 'bkash',
            'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
        ])->assertSessionHasErrors('collection_id');

        $this->assertSame(0, Settlement::count());
    }

    public function test_the_leader_reminds_only_who_has_not_paid_and_not_too_often(): void
    {
        Notification::fake();
        $collection = $this->collect();
        $this->actingAs($this->leader)->post(route('trips.settlements.store', $this->trip), [
            'from_member_id' => $this->member($this->asha)->id, 'amount' => 1000, 'method' => 'cash',
            'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
        ]);

        $this->post(route('trips.collections.nudge', [$this->trip, $collection]))->assertSessionHas('status', 'Reminded 1 traveller.');
        Notification::assertSentTo($this->bilal, TripNotification::class, fn ($n) => str_contains($n->title, 'Bus and cottage: ৳1,000 to Rifat Hasan'));
        Notification::assertNotSentTo($this->asha, TripNotification::class, fn ($n) => str_contains($n->title, 'to Rifat Hasan'));

        $this->post(route('trips.collections.nudge', [$this->trip, $collection]))->assertSessionHas('error');
        $this->actingAs($this->bilal)->post(route('trips.collections.nudge', [$this->trip, $collection]))->assertForbidden();
    }

    public function test_removing_a_collection_keeps_the_payments(): void
    {
        $collection = $this->collect();
        $this->actingAs($this->leader)->post(route('trips.settlements.store', $this->trip), [
            'from_member_id' => $this->member($this->asha)->id, 'amount' => 1000, 'method' => 'cash',
            'settled_on' => now()->toDateString(), 'collection_id' => $collection->id,
        ]);

        $this->delete(route('trips.collections.destroy', [$this->trip, $collection]))->assertRedirect();

        $this->assertModelMissing($collection);
        $this->assertNull(Settlement::firstOrFail()->collection_id);
        $this->assertSame(1000.0, (float) Settlement::sum('amount'));
    }
}
