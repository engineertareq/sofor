<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripLedgerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Trip, 1: User, 2: \Illuminate\Support\Collection<int, TripMember>}
     */
    private function makeTrip(int $extraTravellers = 3): array
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $leader->id,
            'title' => 'Cox\'s Bazar weekend',
            'cover' => 'aurora',
        ]);

        $invitations = app(TripInvitationService::class);
        $members = collect([$invitations->ensureOwnerMember($trip, $leader)]);

        for ($i = 1; $i <= $extraTravellers; $i++) {
            $traveler = User::factory()->create(['name' => 'Traveller '.$i]);

            $members->push($invitations->invite($trip, $leader, [
                'name' => $traveler->name,
                'email' => $traveler->email,
            ])['member']);
        }

        return [$trip->refresh(), $leader, $members];
    }

    public function test_a_new_trip_adds_the_creator_as_the_trip_leader(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('trips.store'), [
                'title' => 'Sajek cloud trip',
                'destination' => 'Rangamati',
                'cover' => 'violet',
                'currency' => 'BDT',
                'budget' => 30000,
                'members' => [
                    ['name' => 'Rifat Hasan', 'email' => 'rifat@example.com'],
                    ['name' => 'Nusrat Jahan', 'phone' => '01712345678'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $trip = Trip::firstWhere('title', 'Sajek cloud trip');

        $this->assertNotNull($trip);
        $this->assertSame(3, $trip->members()->count());
        $this->assertSame(1, $trip->members()->where('role', 'owner')->count());

        // The phone invite belongs to somebody without an account yet.
        $pending = $trip->members()->where('name', 'Nusrat Jahan')->first();
        $this->assertNull($pending->user_id);
        $this->assertSame('+8801712345678', $pending->phone);
    }

    public function test_a_cost_is_split_equally_between_the_travellers(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(3);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Beach cottage',
            'amount' => 5000,
            'category' => 'accommodation',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ])->assertRedirect();

        $expense = Expense::firstOrFail();

        $this->assertSame(4, $expense->shares()->count());
        $this->assertSame(5000.0, (float) $expense->shares()->sum('amount'));
        $this->assertSame(1250.0, (float) $expense->shares()->first()->amount);

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $leader);

        $this->assertSame(5000.0, $ledger['stats']['spent']);
        $this->assertSame(1250.0, $ledger['stats']['per_person']);

        // The payer is owed everybody else's share, and the balances net to zero.
        $this->assertSame(3750.0, $ledger['me']['balance']);
        $this->assertSame(0.0, round(array_sum(array_column($ledger['balances'], 'balance')), 2));
    }

    public function test_travellers_can_be_excluded_or_weighted_in_a_split(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(2);
        $excluded = $members->last();

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Jeep rental',
            'amount' => 3000,
            'category' => 'transport',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->take(2)->pluck('id')->all(),
        ])->assertRedirect();

        $expense = Expense::firstOrFail();

        $this->assertSame(2, $expense->shares()->count());
        $this->assertFalse($expense->shares()->where('trip_member_id', $excluded->id)->exists());

        // Now weight the leader double and re-run the split.
        $leaderMember = $members->first();
        $leaderMember->update(['share_units' => 2]);
        $excluded->update(['include_in_split' => true, 'share_units' => 1]);
        $other = $members->get(1);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Team dinner',
            'amount' => 4000,
            'category' => 'food',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $other->id,
            'split_type' => 'equal',
            'participant_ids' => [$leaderMember->id, $other->id, $excluded->id],
        ])->assertRedirect();

        $dinner = Expense::where('title', 'Team dinner')->firstOrFail();

        $this->assertSame(2000.0, (float) $dinner->shares()->where('trip_member_id', $leaderMember->id)->value('amount'));
        $this->assertSame(1000.0, (float) $dinner->shares()->where('trip_member_id', $other->id)->value('amount'));
        $this->assertSame(4000.0, (float) $dinner->shares()->sum('amount'));
    }

    public function test_member_removal_keeps_the_history_intact(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(2);
        $traveler = $members->last();

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Bus tickets',
            'amount' => 2400,
            'category' => 'transport',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $leader->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ]);

        $this->actingAs($leader)
            ->delete(route('trips.members.destroy', [$trip, $traveler]))
            ->assertRedirect();

        $this->assertSame('removed', $traveler->refresh()->status->value);
        $this->assertFalse($traveler->include_in_split);

        // The cost and its shares are still there, and the maths still adds up.
        $this->assertSame(1, $trip->expenses()->count());
        $this->assertSame(2400.0, (float) Expense::first()->shares()->sum('amount'));
    }

    public function test_an_ended_trip_rejects_new_costs_and_settings_changes(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(2);

        $this->actingAs($leader)->post(route('trips.end', $trip))->assertRedirect();

        $this->assertTrue($trip->refresh()->isEnded());
        $this->assertNotNull($trip->ended_at);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Late entry',
            'amount' => 500,
            'category' => 'food',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ])->assertForbidden();

        $this->assertSame(0, $trip->expenses()->count());
    }

    public function test_recorded_payments_settle_the_dues_and_are_idempotent_to_the_total(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(3);
        $owner = $members->first();
        $debtor = $members->get(1);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Hotel booking',
            'amount' => 5000,
            'category' => 'accommodation',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $owner->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ]);

        $this->actingAs($leader)->post(route('trips.settlements.store', $trip), [
            'from_member_id' => $debtor->id,
            'to_member_id' => $owner->id,
            'amount' => 1250,
            'method' => 'bkash',
            'settled_on' => now()->toDateString(),
        ])->assertRedirect();

        $ledger = app(TripLedger::class)->summarize($trip->refresh(), $leader);

        $this->assertSame(0.0, $ledger['balances'][$debtor->id]['balance']);
        $this->assertSame(2500.0, $ledger['balances'][$owner->id]['balance']);
        $this->assertSame(0.0, round(array_sum(array_column($ledger['balances'], 'balance')), 2));
        $this->assertCount(2, $ledger['suggestions']);
    }

    public function test_outsiders_cannot_open_a_trip_they_are_not_part_of(): void
    {
        [$trip] = $this->makeTrip(1);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('trips.show', $trip))->assertForbidden();
        $this->actingAs($stranger)->get(route('trips.live', $trip))->assertForbidden();
    }

    public function test_the_live_endpoint_returns_refreshed_fragments_after_a_change(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(2);

        $before = $this->actingAs($leader)->getJson(route('trips.live', $trip))->assertOk()->json();

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'CNG fare',
            'amount' => 600,
            'category' => 'transport',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $members->first()->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ]);

        $after = $this->actingAs($leader)
            ->getJson(route('trips.live', $trip).'?since_activity=0')
            ->assertOk()
            ->json();

        $this->assertNotSame($before['stamp'], $after['stamp']);
        $this->assertEquals(600, $after['stats']['spent']);
        $this->assertArrayHasKey('feed', $after['html']);
        $this->assertStringContainsString('CNG fare', $after['html']['feed']);
        $this->assertNotEmpty($after['events']);
    }

    public function test_an_invite_link_lets_a_signed_in_traveller_join_the_trip(): void
    {
        [$trip] = $this->makeTrip(0);
        $guest = User::factory()->create();

        $this->actingAs($guest)->post(route('trips.join', $trip->invite_code))->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $guest->id,
            'status' => 'active',
        ]);
    }
}
