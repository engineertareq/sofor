<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use App\Services\TripLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The taka sign is drawn by its own font everywhere, including the PDF. */
class TakaSignTest extends TestCase
{
    use RefreshDatabase;

    private function tripWithCosts(): array
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);
        $member = app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        foreach ([['Tea', 300, 'food'], ['Cottage', 6000, 'accommodation'], ['Bus', 4400, 'transport']] as [$title, $amount, $category]) {
            $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
                'title' => $title, 'amount' => $amount, 'category' => $category, 'spent_on' => now()->toDateString(),
                'trip_member_id' => $member->id, 'split_type' => 'equal',
            ])->assertSessionHasNoErrors();
        }

        return [$trip, $leader];
    }

    public function test_the_pdf_summary_embeds_a_font_with_the_taka_sign(): void
    {
        [$trip, $leader] = $this->tripWithCosts();

        $pdf = $this->actingAs($leader)->get(route('trips.summary', $trip))->assertOk()->getContent();

        // DejaVu has no taka glyph; the sign must come from the bundled taka font.
        $this->assertStringContainsString('NotoSansBengali', $pdf);
    }

    public function test_where_the_money_went_lists_the_biggest_category_first(): void
    {
        [$trip] = $this->tripWithCosts();

        $totals = collect(app(TripLedger::class)->summarize($trip)['categories'])->pluck('total')->all();

        $this->assertSame([6000.0, 4400.0, 300.0], $totals);
    }
}
