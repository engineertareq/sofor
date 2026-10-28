<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\TripEstimator;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Budgets per person or per group, the 80% / 100% alerts, the pace forecast,
 * and what trips to the same place usually cost.
 */
class BudgetPlanningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Trip, 1: User, 2: User}
     */
    private function tripWithThree(array $attributes = []): array
    {
        $leader = User::factory()->create();
        $friend = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id] + $attributes);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->invite($trip, $leader, ['name' => $friend->name, 'email' => $friend->email]);
        $invitations->invite($trip, $leader, ['name' => 'Cash only Kamal']);

        return [$trip->refresh(), $leader, $friend];
    }

    private function spend(Trip $trip, User $by, float $amount, string $category = 'food'): void
    {
        $this->actingAs($by)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cost '.$amount, 'amount' => $amount, 'category' => $category, 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->where('user_id', $by->id)->value('id'), 'split_type' => 'equal',
            'participant_ids' => $trip->members()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();
    }

    public function test_a_trip_gets_a_per_person_or_a_group_budget(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('trips.store'), [
            'title' => 'Sajek trip', 'cover' => 'aurora', 'currency' => 'BDT',
            'budget_mode' => 'person', 'budget_per_person' => 3000,
        ])->assertSessionHasNoErrors();
        $trip = Trip::latest('id')->first();
        $this->assertSame('3000.00', $trip->budget_per_person);
        $this->assertNull($trip->budget);

        // The old single field still works as a group budget.
        $this->post(route('trips.store'), ['title' => 'Old form', 'cover' => 'aurora', 'currency' => 'BDT', 'budget' => 9000]);
        $trip = Trip::latest('id')->first();
        $this->assertSame('9000.00', $trip->budget);
        $this->assertNull($trip->budget_per_person);

        // Switching to per person clears the group amount, and an empty field clears both.
        $this->put(route('trips.update', $trip), ['title' => 'Old form', 'cover' => 'aurora', 'currency' => 'BDT', 'budget_mode' => 'person', 'budget_per_person' => 2500]);
        $this->assertSame([null, '2500.00'], [$trip->refresh()->budget, $trip->budget_per_person]);
        $this->put(route('trips.update', $trip), ['title' => 'Old form', 'cover' => 'aurora', 'currency' => 'BDT', 'budget_mode' => 'person', 'budget_per_person' => '']);
        $this->assertFalse($trip->refresh()->hasBudget());
    }

    public function test_a_per_person_budget_grows_with_the_group_and_shows_each_share(): void
    {
        [$trip, $leader, $friend] = $this->tripWithThree(['budget_per_person' => 1000]);
        $this->spend($trip, $leader, 1500);

        $this->actingAs($friend)->get(route('trips.show', $trip))
            ->assertOk()
            ->assertSee('Budget ৳1,000 a person')
            ->assertSee('৳3,000 for the group')
            ->assertSee('50%')
            ->assertSee('৳1,500 left')
            ->assertSee('Your share ৳500 of ৳1,000');

        // A couple counts twice.
        $friendRow = $trip->members()->where('user_id', $friend->id)->first();
        $friendRow->update(['share_units' => 2]);
        $this->assertSame(4000.0, app(\App\Services\TripLedger::class)->summarize($trip->refresh())['budget']['total']);
    }

    public function test_the_group_hears_at_80_percent_and_when_over_budget(): void
    {
        Notification::fake();
        [$trip, $leader, $friend] = $this->tripWithThree(['budget_per_person' => 1000]);

        $this->spend($trip, $leader, 2000);
        Notification::assertNotSentTo($friend, TripNotification::class, fn ($n) => str_contains($n->title, 'budget'));

        $this->spend($trip, $leader, 500);   // 2,500 of 3,000
        Notification::assertSentTo($friend, TripNotification::class, fn ($n) => str_contains($n->title, 'has used 83% of its budget'));
        $this->assertSame(80, $trip->refresh()->budget_alert_level);

        $this->spend($trip, $leader, 100);   // still between 80 and 100: no repeat
        $this->assertSame(1, $this->budgetAlerts($friend, 'has used'));

        $this->spend($trip, $leader, 600);   // 3,200: over
        Notification::assertSentTo($leader, TripNotification::class, fn ($n) => str_contains($n->title, 'is over budget') && str_contains($n->body, '৳200 over'));
        $this->assertSame(100, $trip->refresh()->budget_alert_level);

        // Raising the budget resets the alert, so crossing again alerts again.
        $this->actingAs($leader)->put(route('trips.update', $trip), [
            'title' => $trip->title, 'cover' => 'aurora', 'currency' => 'BDT', 'budget_mode' => 'person', 'budget_per_person' => 2000,
        ]);
        $this->assertSame(0, $trip->refresh()->budget_alert_level);
    }

    private function budgetAlerts(User $user, string $text): int
    {
        return collect(Notification::sent($user, TripNotification::class))
            ->filter(fn ($n) => str_contains($n->title, $text))
            ->count();
    }

    public function test_spending_pace_is_projected_to_the_last_day(): void
    {
        [$trip, $leader] = $this->tripWithThree([
            'budget' => 5000,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
        ]);
        $this->spend($trip, $leader, 3000);   // 2 of 4 days gone

        $budget = app(\App\Services\TripLedger::class)->summarize($trip)['budget'];
        $this->assertSame(6000.0, $budget['forecast']);
        $this->assertTrue($budget['forecast_over']);

        $this->actingAs($leader)->get(route('trips.show', $trip))->assertSee('At this pace ৳6,000');
    }

    /* ------------------------------------------------------------------
       Estimates
    ------------------------------------------------------------------ */

    /** An ended trip with the given spend split between the given number of people. */
    private function pastTrip(string $destination, float $spent, int $people, ?int $days = 3, string $currency = 'BDT'): Trip
    {
        $trip = Trip::factory()->ended()->create([
            'destination' => $destination,
            'currency' => $currency,
            'starts_on' => $days ? now()->subDays(30)->toDateString() : null,
            'ends_on' => $days ? now()->subDays(30 - $days + 1)->toDateString() : null,
        ]);

        $payer = null;
        for ($i = 0; $i < $people; $i++) {
            $member = TripMember::create(['trip_id' => $trip->id, 'name' => 'T'.$i, 'role' => $i === 0 ? 'owner' : 'member', 'status' => 'active', 'include_in_split' => true, 'share_units' => 1]);
            $payer ??= $member;
        }

        Expense::create(['trip_id' => $trip->id, 'trip_member_id' => $payer->id, 'title' => 'Bus', 'category' => 'transport', 'amount' => $spent * 0.4, 'spent_on' => now()->subDays(30)->toDateString(), 'split_type' => 'equal']);
        Expense::create(['trip_id' => $trip->id, 'trip_member_id' => $payer->id, 'title' => 'Cottage', 'category' => 'accommodation', 'amount' => $spent * 0.6, 'spent_on' => now()->subDays(30)->toDateString(), 'split_type' => 'equal']);

        return $trip;
    }

    public function test_place_names_are_matched_loosely(): void
    {
        $this->assertSame('coxsbazar', TripEstimator::placeKey("Cox's Bazar, Chattogram"));
        $this->assertSame('coxsbazar', TripEstimator::placeKey('COXS BAZAR'));
        $this->assertSame('sajekvalley', TripEstimator::placeKey('Sajek Valley - Rangamati'));
        $this->assertSame('সাজেক', TripEstimator::placeKey('সাজেক'));
        $this->assertNull(TripEstimator::placeKey('  '));
    }

    public function test_an_estimate_needs_three_finished_trips_to_the_same_place(): void
    {
        $estimator = app(TripEstimator::class);

        $this->pastTrip('Sajek Valley, Rangamati', 12000, 4);   // 3,000 a person
        $this->pastTrip('sajek valley', 16000, 4);              // 4,000
        $this->assertNull($estimator->estimate('Sajek Valley', 'BDT'));

        cache()->flush();
        $this->pastTrip('Sajek Valley', 20000, 4);              // 5,000
        $this->pastTrip('Sylhet', 99000, 3);                    // another place
        $this->pastTrip('Sajek Valley', 90000, 3, 3, 'USD');    // another currency

        $estimate = $estimator->estimate('Sajek valley', 'BDT');
        $this->assertSame(3, $estimate['trips']);
        $this->assertSame(4000.0, $estimate['per_person']);
        $this->assertSame(3500.0, $estimate['low']);
        $this->assertSame(4500.0, $estimate['high']);
        $this->assertSame(3, $estimate['days']);
        $this->assertSame(ExpenseCategory::Accommodation->label(), $estimate['categories'][0]['label']);
        $this->assertSame(60.0, (float) $estimate['categories'][0]['percent']);

        // Five days instead of the usual three.
        $this->assertEqualsWithDelta(6650.0, $estimator->estimate('Sajek Valley', 'BDT', 5)['per_person'], 50);
    }

    public function test_the_new_trip_form_and_the_invite_show_the_estimate(): void
    {
        $this->pastTrip('Bandarban', 9000, 3);
        $this->pastTrip('Bandarban', 9000, 3);
        $this->pastTrip('Bandarban', 9000, 3);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('trips.estimate', ['destination' => 'Bandarban', 'currency' => 'BDT']))
            ->assertOk()
            ->assertJsonPath('estimate.per_person', 3000)
            ->assertJsonPath('estimate.trips', 3);

        $this->getJson(route('trips.estimate', ['destination' => 'Nowhere']))->assertOk()->assertJsonPath('estimate', null);

        $trip = Trip::factory()->create(['destination' => 'Bandarban']);
        $this->actingAs(User::factory()->create())->get($trip->inviteUrl())
            ->assertOk()
            ->assertSee('Trips like this usually cost about')
            ->assertSee('৳3,000 a person');

        $this->get(route('trips.create'))->assertOk()->assertSee('budgetPlanner', false);
    }
}
