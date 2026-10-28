<?php

namespace Tests\Feature;

use App\Models\TravellerDetail;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The checkpoint list: each traveller's ID details for army checkpoints and
 * hotels - encrypted, seen only by them and the leader, deleted after the trip.
 */
class CheckpointListTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $leader;

    private User $asha;

    private User $bilal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan', 'phone' => '+8801711000000']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);
        $this->bilal = User::factory()->create(['name' => 'Bilal Khan']);
        $this->trip = Trip::factory()->create(['user_id' => $this->leader->id, 'title' => 'Sajek', 'destination' => 'Sajek Valley']);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->bilal->name, 'email' => $this->bilal->email]);
        $invitations->invite($this->trip, $this->leader, ['name' => 'Kamal Uddin']);
    }

    private function member(User $user): TripMember
    {
        return $this->trip->members()->where('user_id', $user->id)->firstOrFail();
    }

    /** @return array<string, string> */
    private function details(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Asha Rahman', 'id_type' => 'nid', 'id_number' => '1990 2690 1234 5678 9',
            'date_of_birth' => '1999-04-12', 'gender' => 'female', 'phone' => '01712345678',
            'address' => 'Mirpur, Dhaka', 'blood_group' => 'B+', 'emergency_name' => 'Abdul Karim', 'emergency_phone' => '01812345678',
        ];
    }

    public function test_a_traveller_adds_their_details_and_they_are_stored_encrypted(): void
    {
        $this->actingAs($this->asha)->get(route('trips.manifest', $this->trip))->assertOk()->assertSee('Add me to the list');

        $this->put(route('trips.manifest.mine', $this->trip), $this->details())->assertRedirect(route('trips.manifest', $this->trip));

        $detail = $this->member($this->asha)->travellerDetail;
        $this->assertSame('19902690123456789', $detail->value('id_number'));
        $this->assertSame('+8801712345678', $detail->value('phone'));

        // Nothing readable in the database itself.
        $raw = DB::table('traveller_details')->value('data');
        $this->assertStringNotContainsString('19902690123456789', $raw);
        $this->assertStringNotContainsString('Mirpur', $raw);

        $this->get(route('trips.manifest', $this->trip))->assertSee('NID ••••6789')->assertSee('On the list');
    }

    public function test_bad_ids_and_numbers_are_refused(): void
    {
        $this->actingAs($this->asha);

        $this->put(route('trips.manifest.mine', $this->trip), $this->details(['id_number' => '12345']))->assertSessionHasErrors('id_number');
        $this->put(route('trips.manifest.mine', $this->trip), $this->details(['id_number' => '<script>']))->assertSessionHasErrors('id_number');
        $this->put(route('trips.manifest.mine', $this->trip), $this->details(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->put(route('trips.manifest.mine', $this->trip), $this->details(['date_of_birth' => now()->addDay()->toDateString()]))->assertSessionHasErrors('date_of_birth');

        // A passport number is letters and digits.
        $this->put(route('trips.manifest.mine', $this->trip), $this->details(['id_type' => 'passport', 'id_number' => 'BX0123456']))->assertSessionHasNoErrors();
    }

    public function test_only_the_traveller_and_the_leader_see_the_details(): void
    {
        $this->actingAs($this->asha)->put(route('trips.manifest.mine', $this->trip), $this->details());

        // Another traveller sees that Asha is ready, not her ID.
        $this->actingAs($this->bilal)->get(route('trips.manifest', $this->trip))
            ->assertOk()->assertSee('Details added')->assertDontSee('••••6789')->assertDontSee('Mirpur');
        $this->get(route('trips.manifest.pdf', $this->trip))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('trips.manifest', $this->trip))->assertForbidden();

        $this->actingAs($this->leader)->get(route('trips.manifest', $this->trip))->assertSee('NID ••••6789')->assertDontSee('19902690123456789');

        $pdf = $this->get(route('trips.manifest.pdf', $this->trip));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
    }

    public function test_saving_a_copy_fills_the_next_trip_and_unticking_deletes_it(): void
    {
        $this->actingAs($this->asha)->put(route('trips.manifest.mine', $this->trip), $this->details() + ['remember' => '1']);
        $this->assertSame('19902690123456789', $this->asha->refresh()->travel_profile['id_number']);
        $this->assertArrayNotHasKey('travel_profile', $this->asha->toArray());

        $next = Trip::factory()->create(['user_id' => $this->leader->id]);
        app(TripInvitationService::class)->ensureOwnerMember($next, $this->leader);
        app(TripInvitationService::class)->invite($next, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);

        $this->get(route('trips.manifest', $next))->assertSee('19902690123456789', false)->assertSee('Filled in from the copy you saved');

        $this->put(route('trips.manifest.mine', $this->trip), $this->details());
        $this->assertNull($this->asha->refresh()->travel_profile);
    }

    public function test_the_leader_adds_travellers_without_an_account_and_reminds_the_rest(): void
    {
        Notification::fake();
        $kamal = $this->trip->members()->where('name', 'Kamal Uddin')->firstOrFail();

        $this->actingAs($this->asha)->put(route('trips.manifest.member', [$this->trip, $kamal]), $this->details(['full_name' => 'Kamal Uddin']))->assertForbidden();
        $this->actingAs($this->leader)->put(route('trips.manifest.member', [$this->trip, $this->member($this->asha)]), $this->details())->assertNotFound();

        $this->put(route('trips.manifest.member', [$this->trip, $kamal]), $this->details(['full_name' => 'Kamal Uddin', 'form' => 'member-'.$kamal->id]))->assertSessionHasNoErrors();
        $this->assertSame('Kamal Uddin', $kamal->travellerDetail->value('full_name'));

        $this->put(route('trips.manifest.trip', $this->trip), ['vehicle' => 'Chander gari', 'vehicle_number' => 'Kha 11-0234', 'route' => 'Khagrachari - Sajek']);
        $this->assertSame('Chander gari', $this->trip->refresh()->checkpoint['vehicle']);

        $this->post(route('trips.manifest.remind', $this->trip))->assertSessionHas('status', 'Reminded 2 travellers.');
        Notification::assertSentTo([$this->asha, $this->bilal], TripNotification::class, fn ($n) => str_contains($n->title, 'checkpoint list'));
        $this->post(route('trips.manifest.remind', $this->trip))->assertSessionHas('error');
    }

    public function test_the_list_is_deleted_two_weeks_after_the_trip(): void
    {
        $this->actingAs($this->asha)->put(route('trips.manifest.mine', $this->trip), $this->details() + ['remember' => '1']);
        $this->actingAs($this->leader)->put(route('trips.manifest.trip', $this->trip), ['vehicle' => 'Bus']);
        $this->trip->forceFill(['status' => 'ended', 'ended_at' => now()->subDays(10)])->save();

        $this->artisan('sofor:purge-checkpoint-lists')->expectsOutputToContain('Cleared 0 traveller records');
        $this->assertSame(1, TravellerDetail::count());

        $this->travel(5)->days();
        $this->artisan('sofor:purge-checkpoint-lists')->expectsOutputToContain('Cleared 1 traveller record from 1 trip');

        $this->assertSame(0, TravellerDetail::count());
        $this->assertNull($this->trip->refresh()->checkpoint);
        // The traveller's own saved copy is theirs, not the trip's.
        $this->assertNotNull($this->asha->refresh()->travel_profile);
    }
}
