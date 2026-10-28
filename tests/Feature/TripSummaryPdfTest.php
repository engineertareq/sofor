<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Download PDF summary" button on a trip has to produce a real PDF file,
 * not the browser's print dialog, and only for people who may see the trip.
 */
class TripSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Trip, 1: User, 2: \Illuminate\Support\Collection<int, TripMember>}
     */
    private function makeTrip(int $extraTravellers = 2): array
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
            $traveller = User::factory()->create(['name' => 'Traveller '.$i]);

            $members->push($invitations->invite($trip, $leader, [
                'name' => $traveller->name,
                'email' => $traveller->email,
            ])['member']);
        }

        return [$trip->refresh(), $leader, $members];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TripMember>  $members
     */
    private function addExpense(Trip $trip, User $leader, $members, string $title, float $amount): void
    {
        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => $title,
            'amount' => $amount,
            'category' => 'transport',
            'spent_on' => now()->toDateString(),
            'trip_member_id' => $leader->id,
            'split_type' => 'equal',
            'participant_ids' => $members->pluck('id')->all(),
        ])->assertRedirect();
    }

    public function test_the_leader_downloads_a_real_pdf_file(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();
        $this->addExpense($trip, $leader, $members, 'Bus tickets', 2400);

        $response = $this->actingAs($leader)->get(route('trips.summary', $trip));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // It must be an attachment with a readable name, not an inline print view.
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('sofor-coxs-bazar-weekend-summary.pdf', $disposition);

        $body = $response->getContent();

        // A genuine PDF starts with the %PDF- signature and is more than a stub.
        $this->assertSame('%PDF-', substr($body, 0, 5));
        $this->assertGreaterThan(10_000, strlen($body), 'The PDF looks too small to contain a ledger.');
    }

    public function test_the_pdf_still_works_for_an_ended_trip(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();
        $this->addExpense($trip, $leader, $members, 'Cottage', 6000);

        $this->actingAs($leader)->post(route('trips.end', $trip))->assertRedirect();

        $response = $this->actingAs($leader)->get(route('trips.summary', $trip->refresh()));

        $response->assertOk();
        $this->assertSame('%PDF-', substr($response->getContent(), 0, 5));
    }

    public function test_the_pdf_still_works_for_a_trip_with_no_costs_yet(): void
    {
        [$trip, $leader] = $this->makeTrip(0);

        $response = $this->actingAs($leader)->get(route('trips.summary', $trip));

        $response->assertOk();
        $this->assertSame('%PDF-', substr($response->getContent(), 0, 5));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        [$trip] = $this->makeTrip();

        $this->get(route('trips.summary', $trip))->assertRedirect(route('login'));
    }

    public function test_a_traveller_who_is_not_on_the_trip_is_refused(): void
    {
        [$trip] = $this->makeTrip();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('trips.summary', $trip))
            ->assertForbidden();
    }

    public function test_the_summary_template_prints_the_ledger_totals(): void
    {
        [$trip, $leader, $members] = $this->makeTrip(2);
        $this->addExpense($trip, $leader, $members, 'Bus tickets', 2400);
        $this->addExpense($trip, $leader, $members, 'Cottage', 3600);

        $ledger = app(TripLedger::class)->summarize($trip, $leader);

        $html = view('trips.pdf.summary', [
            'trip' => $trip,
            'ledger' => $ledger,
            'expenses' => $trip->expenses()->with(['payer', 'shares.member', 'author'])->get(),
            'settlements' => $trip->settlements()->with(['from', 'to'])->take(30)->get(),
            'activities' => $trip->activities()->with(['user', 'member'])->take(40)->get(),
            'currentMember' => $members->first(),
            'canAddExpense' => false,
            'canManage' => false,
            'canRecord' => false,
            'categories' => [],
            'paymentMethods' => [],
            'inviteUrl' => $trip->inviteUrl(),
            'bkashEnabled' => false,
            'bkashMode' => 'redirect',
            'bkashScriptUrl' => null,
            'bkashChargePercent' => 0,
            'bkashSandbox' => true,
        ])->render();

        $this->assertStringContainsString(e($trip->title), $html);

        // 2400 + 3600 across 3 travellers, in whole taka the way Sofor writes money.
        // (The sign sits in its own font span, so compare the text a reader sees.)
        $text = strip_tags($html);
        $this->assertStringContainsString('৳6,000', $text, 'The total spend is missing.');
        $this->assertStringContainsString('৳2,000', $text, 'The per-person share is missing.');
        $this->assertStringContainsString('<span class="tk">৳</span>', $html, 'The taka sign must use the taka font.');

        // The document must never promise money movement or leak an em dash.
        $this->assertStringNotContainsString('—', $html, 'Copy must use plain hyphens, never an em dash.');
        $this->assertStringContainsString('Sofor never touches the money', $html);
    }

    public function test_the_trip_page_offers_a_pdf_download_instead_of_printing(): void
    {
        [$trip, $leader, $members] = $this->makeTrip();
        $this->addExpense($trip, $leader, $members, 'Bus tickets', 2400);

        $response = $this->actingAs($leader)->get(route('trips.show', $trip));

        $response->assertOk();
        $response->assertSee(route('trips.summary', $trip), false);
        $response->assertSee('Download PDF summary');

        // The old behaviour sent people to the browser print dialog.
        $response->assertDontSee('window.print', false);
    }
}
