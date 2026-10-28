<?php

namespace Tests\Feature;

use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\PortfolioService;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contributions are money handed over inside a trip. Any traveller can give any
 * amount, as many times as they like, and every entry is summed and applied to
 * the balances of the whole trip.
 */
class ContributionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A trip where the leader covered 5,000 for four travellers, so everybody
     * else owes 1,250.
     *
     * @return array{0: Trip, 1: User, 2: \Illuminate\Support\Collection<int, TripMember>}
     */
    private function makeTrip(): array
    {
        $leader = User::factory()->create(['name' => 'Trip Leader']);
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        $invitations = app(TripInvitationService::class);
        $members = collect([$invitations->ensureOwnerMember($trip, $leader)]);

        foreach (range(1, 3) as $index) {
            $traveler = User::factory()->create(['name' => 'Traveller '.$index]);

            $members->push($invitations->invite($trip, $leader, [
                'name' => $traveler->name,
                'email' => $traveler->email,
            ])['member']);
        }

        $trip = $trip->refresh();

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cottage booking',
            'amount' => 5000,
            'category' => 'accommodation',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ]);

        return [$trip, $leader, $members->each->refresh()];
    }

    public function test_a_traveller_can_pay_in_several_times_and_every_entry_counts(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        $traveler = $members->get(1);

        // Two separate payments from the same person.
        foreach ([500, 750] as $amount) {
            $this->actingAs($traveler->user)->post(route('trips.settlements.store', $trip), [
                'from_member_id' => $traveler->id,
                'to_member_id' => $members->first()->id,
                'amount' => $amount,
                'method' => 'bkash',
                'settled_on' => now()->toDateString(),
            ])->assertRedirect();
        }

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $traveler->user);

        $this->assertSame(2, Settlement::count());
        $this->assertSame(1250.0, $ledger['stats']['contribution_total']);
        $this->assertSame(2, $ledger['stats']['contribution_count']);
        $this->assertSame(1, $ledger['stats']['contributors']);

        // 1,250 due minus 1,250 paid in leaves this traveller square.
        $this->assertSame(1250.0, $ledger['balances'][$traveler->id]['contributed']);
        $this->assertSame(0.0, $ledger['balances'][$traveler->id]['balance']);
        $this->assertSame(2, $ledger['balances'][$traveler->id]['contribution_count']);
    }

    public function test_contributions_from_any_traveller_are_applied_to_the_whole_trip(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        $first = $members->get(1);
        $second = $members->get(2);
        $owner = $members->first();

        $this->actingAs($first->user)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $first->id,
            'to_member_id' => $owner->id,
            'amount' => 600,
            'method' => 'bkash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($second->user)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $second->id,
            'to_member_id' => $owner->id,
            'amount' => 1250,
            'method' => 'cash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $owner->user);

        $this->assertSame(1850.0, $ledger['stats']['contribution_total']);
        $this->assertSame(2, $ledger['stats']['contributors']);
        $this->assertSame(650.0, $ledger['balances'][$first->id]['balance'] * -1);
        $this->assertSame(0.0, $ledger['balances'][$second->id]['balance']);
        $this->assertSame(1900.0, $ledger['balances'][$owner->id]['balance']);

        // The whole trip still nets to zero.
        $this->assertSame(0.0, round(array_sum(array_column($ledger['balances'], 'balance')), 2));
    }

    public function test_a_contribution_without_a_recipient_clears_the_largest_due_first(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        $traveler = $members->get(1);

        // No to_member_id at all - Sofor should route it to the traveller who is owed.
        $this->actingAs($traveler->user)->post(route('trips.settlements.store', $trip), [
            'amount' => 1250,
            'method' => 'bkash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $settlement = Settlement::firstOrFail();

        $this->assertSame($traveler->id, $settlement->from_member_id);
        $this->assertSame($members->first()->id, $settlement->to_member_id);

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $traveler->user);

        $this->assertSame(0.0, $ledger['balances'][$traveler->id]['balance']);
    }

    public function test_a_traveller_who_is_owed_money_hands_it_to_the_largest_creditor(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        // The leader fronted everything, so a contribution with no recipient has
        // to land with them rather than bouncing back to the payer.
        $this->actingAs($members->first()->user)->post(route('trips.settlements.store', $trip), [
            'amount' => 900,
            'method' => 'cash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $settlement = Settlement::firstOrFail();

        $this->assertSame($members->first()->id, $settlement->from_member_id);
        $this->assertNotSame($settlement->from_member_id, $settlement->to_member_id);

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $leader);

        $this->assertSame(900.0, $ledger['stats']['contribution_total']);
        $this->assertSame(0.0, round(array_sum(array_column($ledger['balances'], 'balance')), 2));
    }

    public function test_a_traveller_can_still_pay_in_after_the_trip_has_ended(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        $this->actingAs($leader)->post(route('trips.end', $trip))->assertRedirect();

        $traveler = $members->get(2);

        $this->actingAs($traveler->user)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $traveler->id,
            'to_member_id' => $members->first()->id,
            'amount' => 1250,
            'method' => 'nagad',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $traveler->user);

        $this->assertSame(1250.0, $ledger['stats']['contribution_total']);
        $this->assertSame(0.0, $ledger['balances'][$traveler->id]['balance']);
    }

    public function test_outsiders_cannot_record_a_contribution(): void
    {
        [$trip] = $this->makeTrip();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('trips.settlements.store', $trip), [
            'amount' => 500,
            'method' => 'cash',
            'settled_on' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Settlement::count());
    }

    public function test_contributions_flow_into_the_dashboard_totals(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();

        $traveler = $members->get(1);
        $owner = $members->first();

        $before = app(PortfolioService::class)->forUser($traveler->user)['totals'];

        $this->assertSame(1250.0, $before['due']);
        $this->assertSame(0.0, $before['settled_out']);

        $this->actingAs($traveler->user)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $traveler->id,
            'to_member_id' => $owner->id,
            'amount' => 1250,
            'method' => 'bkash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $after = app(PortfolioService::class)->forUser($traveler->user->refresh())['totals'];

        // The contribution shows up as money paid in and clears the due.
        $this->assertSame(1250.0, $after['settled_out']);
        $this->assertSame(0.0, $after['due']);
        $this->assertSame(1250.0, $after['settled_out'] - $before['settled_out']);
    }
}
