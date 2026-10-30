<?php

namespace Tests\Feature;

use App\Enums\TourBookingStatus;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\TourPackage;
use App\Models\TourPayment;
use App\Models\TourRegistration;
use App\Models\User;
use App\Notifications\TourBooked;
use App\Notifications\TripNotification;
use App\Services\TourAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Organiser mode: a tour sold to the public, booked from one link, paid to the organiser directly. */
class OrganisedToursTest extends TestCase
{
    use RefreshDatabase;

    /** A tour with a regular package and a couple's one (two seats). */
    private function tour(array $attributes = [], TourStatus $status = TourStatus::Open): Tour
    {
        $tour = new Tour(array_merge(['title' => 'Sajek Valley', 'destination' => 'Sajek', 'cover' => 'mint', 'capacity' => 10, 'show_account' => true], $attributes));
        $tour->organiser()->associate(User::factory()->create(['sms_balance' => 20]));
        $tour->status = $status;
        $tour->save();

        $tour->packages()->create(['name' => 'Regular', 'price' => 6500, 'seats' => 1, 'advance' => 2000, 'sort_order' => 0]);
        $tour->packages()->create(['name' => 'Couple', 'price' => 12000, 'seats' => 2, 'sort_order' => 1]);

        return $tour->fresh(['packages', 'organiser']);
    }

    private function package(Tour $tour, string $name): TourPackage
    {
        return $tour->packages->firstWhere('name', $name);
    }

    /** A booking through the public form; returns the response. */
    private function book(Tour $tour, string $package = 'Regular', int $quantity = 1, string $phone = '01711000001', array $extra = [])
    {
        return $this->post(route('tours.register', $tour->code), array_merge([
            'name' => 'Rahim Uddin', 'phone' => $phone, 'tour_package_id' => $this->package($tour, $package)->id, 'quantity' => $quantity,
        ], $extra));
    }

    public function test_an_organiser_sets_up_a_tour_and_opens_it(): void
    {
        $organiser = User::factory()->create();

        $response = $this->actingAs($organiser)->post(route('tours.store'), [
            'title' => 'Bandarban, 3 days',
            'destination' => 'Nilgiri',
            'cover' => 'forest',
            'starts_on' => '2026-12-10',
            'ends_on' => '2026-12-12',
            'capacity' => 40,
            'contact_phone' => '01811-000000',
            'show_account' => '1',
            'publish' => '1',
            'packages' => [
                ['name' => 'Regular', 'price' => '6500', 'seats' => '1', 'advance' => '2000'],
                ['name' => '', 'price' => '', 'seats' => '1'],
                ['name' => 'Couple', 'price' => '12000', 'seats' => '2', 'advance' => ''],
            ],
        ]);

        $tour = Tour::first();
        $response->assertRedirect(route('tours.show', $tour));
        $this->assertSame(TourStatus::Open, $tour->status);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $tour->code);
        $this->assertSame('+8801811000000', $tour->contact_phone);
        $this->assertSame(['Regular', 'Couple'], $tour->packages->pluck('name')->all(), 'The empty row is not a package.');
        $this->assertNull($tour->packages[1]->advance);

        $this->get(route('tours.show', $tour))->assertOk()->assertSee($tour->publicUrl(), false);
        $this->get(route('tours.index'))->assertOk()->assertSee('Bandarban, 3 days');
    }

    public function test_a_draft_is_only_shown_to_its_organiser(): void
    {
        $tour = $this->tour(status: TourStatus::Draft);

        $this->get(route('tours.public', $tour->code))->assertNotFound();
        $this->actingAs($tour->organiser)->get(route('tours.public', $tour->code))->assertOk()->assertSee('Preview');
    }

    public function test_anyone_books_from_the_public_page_without_an_account(): void
    {
        Notification::fake();
        $tour = $this->tour();

        $this->get(route('tours.public', $tour->code))->assertOk()->assertSee('Sajek Valley')->assertSee('Book your seat');

        $response = $this->book($tour, 'Couple', 2, '01711-000001', ['email' => 'rahim@example.com', 'note' => 'Two couples']);

        $registration = TourRegistration::first();
        $response->assertRedirect(route('tours.ticket', $registration->token));
        $this->assertSame(TourBookingStatus::Booked, $registration->status);
        $this->assertSame('+8801711000001', $registration->phone);
        $this->assertSame(4, $registration->seats);
        $this->assertEquals(24000, $registration->total);
        $this->assertSame(6, $tour->seatsLeft());

        Notification::assertSentTo($tour->organiser, TripNotification::class);
        Notification::assertSentOnDemand(TourBooked::class);

        // A later price change never moves a booking's total.
        $this->package($tour, 'Couple')->update(['price' => 15000]);
        $this->assertEquals(24000, $registration->fresh()->total);
    }

    public function test_a_full_tour_puts_new_bookings_on_the_waitlist(): void
    {
        $tour = $this->tour(['capacity' => 4]);

        $this->book($tour, 'Couple', 2, '01711000001');
        $this->book($tour, 'Regular', 1, '01711000002');

        [$first, $second] = TourRegistration::orderBy('id')->get()->all();
        $this->assertSame(TourBookingStatus::Booked, $first->status);
        $this->assertSame(TourBookingStatus::Waitlisted, $second->status);

        // Seats free up; the organiser gives them to the waitlisted booking.
        $this->actingAs($tour->organiser)->patch(route('tours.bookings.update', [$tour, $first]), ['action' => 'cancel'])->assertRedirect();
        $this->patch(route('tours.bookings.update', [$tour, $second]), ['action' => 'book'])->assertSessionHas('status');

        $this->assertSame(TourBookingStatus::Booked, $second->fresh()->status);
        $this->assertSame(3, $tour->seatsLeft());
    }

    public function test_one_booking_per_number_and_none_once_closed(): void
    {
        $tour = $this->tour();

        $this->book($tour, phone: '01711000001');
        $this->book($tour, phone: '+880 1711-000001')->assertSessionHas('error');
        $this->assertSame(1, TourRegistration::count());

        $tour->update(['closes_on' => now()->subDay()]);
        $this->book($tour, phone: '01711000002')->assertSessionHas('error');

        $tour->update(['closes_on' => null]);
        $tour->status = TourStatus::Closed;
        $tour->save();
        $this->book($tour, phone: '01711000003')->assertSessionHas('error');

        $this->assertSame(1, TourRegistration::count());
    }

    public function test_bots_filling_the_hidden_field_are_turned_away(): void
    {
        $tour = $this->tour();

        $this->book($tour, extra: ['website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->assertSame(0, TourRegistration::count());
    }

    public function test_a_reported_payment_waits_for_the_organiser(): void
    {
        Notification::fake();
        $tour = $this->tour();
        $this->book($tour);
        $registration = TourRegistration::first();

        $this->post(route('tours.ticket.paid', $registration->token), ['amount' => 2000, 'method' => 'bkash', 'reference' => '8N7A2K1Q', 'sender' => '01711000001'])
            ->assertSessionHas('status');

        $payment = TourPayment::first();
        $this->assertSame(TourPayment::STATUS_PENDING, $payment->status);
        $this->assertSame(0.0, $registration->fresh()->paid(), 'Nothing counts until it is confirmed.');
        Notification::assertSentTo($tour->organiser, TripNotification::class);

        $this->actingAs($tour->organiser)->get(route('tours.show', $tour))->assertSee('8N7A2K1Q');
        $this->post(route('tours.payments.confirm', [$tour, $payment]))->assertSessionHas('status');

        $registration = $registration->fresh();
        $this->assertSame(2000.0, $registration->paid());
        $this->assertSame(4500.0, $registration->due());
        $this->assertSame(TourRegistration::STATE_PARTLY, $registration->paymentState());
    }

    public function test_the_organiser_records_cash_and_refunds_never_exceed_what_was_paid(): void
    {
        $tour = $this->tour();
        $this->book($tour);
        $registration = TourRegistration::first();
        $this->actingAs($tour->organiser);

        $this->post(route('tours.payments.store', [$tour, $registration]), ['type' => 'payment', 'amount' => 6500, 'method' => 'cash']);
        $this->assertSame(TourRegistration::STATE_PAID, $registration->fresh()->paymentState());

        $this->post(route('tours.payments.store', [$tour, $registration]), ['type' => 'refund', 'amount' => 7000, 'method' => 'bkash'])->assertSessionHas('error');
        $this->post(route('tours.payments.store', [$tour, $registration]), ['type' => 'refund', 'amount' => 1500, 'method' => 'bkash'])->assertSessionHas('status');

        $this->assertSame(5000.0, $registration->fresh()->paid());
    }

    public function test_the_account_adds_up(): void
    {
        $tour = $this->tour();
        $this->book($tour, 'Regular', 2, '01711000001');   // 13,000
        $this->book($tour, 'Couple', 1, '01711000002');    // 12,000
        $this->book($tour, 'Regular', 1, '01711000003');   // 6,500, cancelled after paying 2,000
        [$a, $b, $c] = TourRegistration::orderBy('id')->get()->all();
        $this->actingAs($tour->organiser);

        $this->post(route('tours.payments.store', [$tour, $a]), ['type' => 'payment', 'amount' => 13000, 'method' => 'bkash']);
        $this->post(route('tours.payments.store', [$tour, $b]), ['type' => 'payment', 'amount' => 5000, 'method' => 'nagad']);
        $this->post(route('tours.payments.store', [$tour, $c]), ['type' => 'payment', 'amount' => 2000, 'method' => 'cash']);
        $this->patch(route('tours.bookings.update', [$tour, $c]), ['action' => 'cancel']);
        $this->post(route('tours.payments.store', [$tour, $c]), ['type' => 'refund', 'amount' => 500, 'method' => 'cash']);
        $this->post(route('tours.costs.store', $tour), ['title' => 'Bus both ways', 'amount' => 9000, 'category' => 'transport', 'spent_on' => now()->toDateString()]);
        $this->post(route('tours.costs.store', $tour), ['title' => 'Cottage', 'amount' => 6000, 'category' => 'accommodation', 'spent_on' => now()->toDateString()]);

        $account = app(TourAccount::class)->summarize($tour->fresh());

        $this->assertSame(4, $account['seats']);
        $this->assertSame(25000.0, $account['expected']);
        $this->assertSame(19500.0, $account['received']);       // 13,000 + 5,000 + 2,000 - 500
        $this->assertSame(500.0, $account['refunded']);
        $this->assertSame(7000.0, $account['outstanding']);     // the couple's 12,000 less 5,000
        $this->assertSame(1500.0, $account['refunds_owed']);    // the cancelled booking: 2,000 paid, 500 back
        $this->assertSame(15000.0, $account['spent']);
        $this->assertSame(4500.0, $account['balance']);         // 19,500 - 15,000
        $this->assertSame(10000.0, $account['projected']);      // + 7,000 still due - 1,500 to refund
        $this->assertSame('transport', $account['by_category'][0]['category']->value);
    }

    public function test_only_the_organiser_runs_a_tour(): void
    {
        $tour = $this->tour();
        $other = $this->tour(['title' => 'Another tour']);
        $this->book($tour);
        $registration = TourRegistration::first();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('tours.show', $tour))->assertForbidden();
        $this->patch(route('tours.status', $tour), ['status' => 'closed'])->assertForbidden();
        $this->get(route('tours.export', $tour))->assertForbidden();

        // A booking cannot be reached through another tour, even its own organiser's.
        $this->actingAs($other->organiser)->post(route('tours.payments.store', [$other, $registration]), ['type' => 'payment', 'amount' => 100, 'method' => 'cash'])->assertNotFound();
        $this->assertSame(0, TourPayment::count());
    }

    public function test_status_only_moves_where_it_can(): void
    {
        $tour = $this->tour(status: TourStatus::Draft);
        $this->actingAs($tour->organiser);

        $this->patch(route('tours.status', $tour), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->patch(route('tours.status', $tour), ['status' => 'open'])->assertSessionHas('status');
        $this->patch(route('tours.status', $tour), ['status' => 'closed']);
        $this->patch(route('tours.status', $tour), ['status' => 'completed']);

        $this->assertSame(TourStatus::Completed, $tour->fresh()->status);
    }

    public function test_the_booking_page_is_private_and_shows_the_account_only_when_shared(): void
    {
        $tour = $this->tour();
        $this->book($tour);
        $registration = TourRegistration::first();
        $tour->expenses()->create(['title' => 'Bus', 'amount' => 3000, 'category' => 'transport', 'spent_on' => now()]);

        $this->get(route('tours.ticket', $registration->token))
            ->assertOk()
            ->assertSee('Hi Rahim')
            ->assertSee('Still to pay')
            ->assertSee('Pay at least')
            ->assertSee('Where the money went')
            // The statement only: the organiser's package sales, projection and switch stay on the dashboard.
            ->assertDontSee('Packages sold')
            ->assertDontSee('Once everyone pays')
            ->assertDontSee(route('tours.sharing', $tour), false);

        $tour->update(['show_account' => false]);
        $this->get(route('tours.ticket', $registration->token))->assertDontSee('Where the money went');

        $this->get('/r/'.str_repeat('a', 32))->assertNotFound();
    }

    public function test_a_reminder_text_comes_out_of_the_organisers_sms_wallet(): void
    {
        $tour = $this->tour();
        $this->book($tour);
        $registration = TourRegistration::first();
        $this->actingAs($tour->organiser);

        $this->post(route('tours.bookings.remind', [$tour, $registration]))->assertSessionHas('status');

        $this->assertLessThan(20, (float) $tour->organiser->fresh()->sms_balance);
        $this->assertNotNull($registration->fresh()->reminded_at);

        // Not again straight away.
        $this->post(route('tours.bookings.remind', [$tour, $registration]))->assertSessionHas('error');
    }

    public function test_the_bookings_come_out_as_a_spreadsheet(): void
    {
        $tour = $this->tour();
        $this->book($tour, 'Couple');

        $csv = $this->actingAs($tour->organiser)->get(route('tours.export', $tour))->assertOk()->streamedContent();

        $this->assertStringContainsString('Name,Phone,Email,Package', $csv);
        $this->assertStringContainsString('"Rahim Uddin","+880 1711-000001",,Couple,1,2,12000.00,0.00,12000.00,booked', $csv);
    }

    public function test_a_package_somebody_booked_cannot_be_removed(): void
    {
        $tour = $this->tour();
        $this->book($tour, 'Couple');
        $regular = $this->package($tour, 'Regular');

        $this->actingAs($tour->organiser)->put(route('tours.update', $tour), [
            'title' => 'Sajek Valley', 'cover' => 'mint',
            'packages' => [['id' => $regular->id, 'name' => 'Regular', 'price' => 7000, 'seats' => 1]],
        ])->assertSessionHasErrors('packages');

        $this->put(route('tours.update', $tour), [
            'title' => 'Sajek Valley', 'cover' => 'mint',
            'packages' => [
                ['id' => $regular->id, 'name' => 'Regular', 'price' => 7000, 'seats' => 1],
                ['id' => $this->package($tour, 'Couple')->id, 'name' => 'Couple', 'price' => 12500, 'seats' => 2],
            ],
        ])->assertRedirect(route('tours.show', $tour));

        $this->assertEquals(7000, $regular->fresh()->price);
    }

    public function test_people_cancel_their_own_booking_only_before_paying(): void
    {
        $tour = $this->tour();
        $this->book($tour, phone: '01711000001');
        $this->book($tour, phone: '01711000002');
        [$unpaid, $paid] = TourRegistration::orderBy('id')->get()->all();
        $this->post(route('tours.ticket.paid', $paid->token), ['amount' => 500, 'method' => 'bkash']);

        $this->post(route('tours.ticket.cancel', $unpaid->token))->assertSessionHas('status');
        $this->post(route('tours.ticket.cancel', $paid->token))->assertSessionHas('error');

        $this->assertSame(TourBookingStatus::Cancelled, $unpaid->fresh()->status);
        $this->assertSame(TourBookingStatus::Booked, $paid->fresh()->status);
    }

    public function test_the_pages_read_in_bangla(): void
    {
        config(['app.interface_locale' => 'bn']);
        $tour = $this->tour();
        $this->book($tour);
        $registration = TourRegistration::first();

        $this->get(route('tours.public', $tour->code))->assertOk()->assertSee('<html lang="bn">', false);
        $this->get(route('tours.ticket', $registration->token))->assertOk()->assertSee('৬,৫০০');
        $this->actingAs($tour->organiser)->get(route('tours.show', $tour))->assertOk();
    }
}
