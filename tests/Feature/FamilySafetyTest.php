<?php

namespace Tests\Feature;

use App\Models\SmsTransaction;
use App\Models\Trip;
use App\Models\TripCheckin;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Check-ins, and the private family page that shows them. */
class FamilySafetyTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $leader;

    private User $asha;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.driver' => 'smsnetbd',
            'services.sms.smsnetbd.url' => 'https://api.sms.test',
            'services.sms.smsnetbd.key' => 'test-key',
            'services.sms.price' => 0.45,
        ]);
        Http::fake(['api.sms.test/*' => Http::response(['error' => 0, 'msg' => 'ok', 'data' => ['request_id' => 1]])]);

        $this->leader = User::factory()->create(['name' => 'Rifat Hasan']);
        $this->asha = User::factory()->create(['name' => 'Asha Rahman']);
        $this->trip = Trip::factory()->create(['user_id' => $this->leader->id, 'title' => 'Sajek', 'destination' => 'Sajek Valley, Rangamati']);

        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($this->trip, $this->leader);
        $invitations->invite($this->trip, $this->leader, ['name' => $this->asha->name, 'email' => $this->asha->email]);
    }

    private function member(User $user): TripMember
    {
        return $this->trip->members()->where('user_id', $user->id)->firstOrFail();
    }

    public function test_a_traveller_checks_in_and_the_group_hears(): void
    {
        Notification::fake();

        $this->actingAs($this->asha)->post(route('trips.checkins.store', $this->trip), [
            'place' => 'Reached Khagrachari', 'note' => 'Bus was late', 'latitude' => '23.119', 'longitude' => '91.984',
        ])->assertSessionHas('status');

        $checkin = TripCheckin::firstOrFail();
        $this->assertSame($this->member($this->asha)->id, $checkin->trip_member_id);
        $this->assertStringContainsString('query=23.119,91.984', $checkin->mapUrl());
        Notification::assertSentTo($this->leader, TripNotification::class, fn ($n) => $n->title === 'Asha checked in');

        $this->actingAs($this->leader)->get(route('trips.show', $this->trip))
            ->assertSee('Reached Khagrachari')->assertSee('Bus was late')
            // The browser may only ask for a location if the page allows it for itself.
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), interest-cohort=()');

        // Half a location is refused, and outsiders cannot check in.
        $this->actingAs($this->asha)->post(route('trips.checkins.store', $this->trip), ['place' => 'Somewhere', 'latitude' => '23.1'])
            ->assertSessionHasErrors('longitude');
        $this->actingAs(User::factory()->create())->post(route('trips.checkins.store', $this->trip), ['place' => 'Here'])->assertForbidden();
    }

    public function test_the_family_link_shows_the_trip_without_an_account(): void
    {
        $this->actingAs($this->asha)->post(route('trips.family.link', $this->trip))->assertSessionHas('status');
        $link = route('family.show', $this->member($this->asha)->family_token);

        $this->post(route('trips.checkins.store', $this->trip), ['place' => 'Safe at the cottage']);
        auth()->logout();

        $this->get($link)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Asha is travelling')
            ->assertSee('Safe at the cottage')
            ->assertSee('2 travellers')
            ->assertDontSee($this->asha->email);

        // A new link retires the old one; turning it off retires it too.
        $this->actingAs($this->asha)->post(route('trips.family.link', $this->trip));
        auth()->logout();
        $this->get($link)->assertNotFound();

        $this->actingAs($this->asha)->delete(route('trips.family.disable', $this->trip));
        $this->assertNull($this->member($this->asha)->family_token);
    }

    public function test_after_the_trip_the_family_page_stops_showing_check_ins(): void
    {
        $this->actingAs($this->asha)->post(route('trips.family.link', $this->trip));
        $this->post(route('trips.checkins.store', $this->trip), ['place' => 'Home safe']);
        $link = route('family.show', $this->member($this->asha)->family_token);
        $this->trip->forceFill(['status' => 'ended', 'ended_at' => now()])->save();

        $this->get($link)->assertSee('Home safe');

        $this->travel(8)->days();
        $this->get($link)->assertSee('This trip is over')->assertDontSee('Home safe');
    }

    public function test_a_check_in_can_be_texted_to_family_from_the_wallet(): void
    {
        $this->asha->forceFill(['sms_balance' => 1])->save();
        $this->actingAs($this->asha)->put(route('trips.family.phone', $this->trip), ['family_phone' => '01811111111'])->assertSessionHas('status');

        $this->post(route('trips.checkins.store', $this->trip), ['place' => 'Reached Sajek', 'sms' => '1'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Your family got a text'));

        Http::assertSent(fn ($request) => $request['to'] === '8801811111111'
            && str_contains($request['msg'], 'Asha checked in - Reached Sajek')
            && str_contains($request['msg'], '/f/'));
        $this->assertSame(0.55, (float) $this->asha->refresh()->sms_balance);
        $this->assertSame(1, SmsTransaction::where('type', 'charge')->count());

        // Out of credit: the check-in still counts, the text does not go.
        $this->asha->forceFill(['sms_balance' => 0])->save();
        $this->post(route('trips.checkins.store', $this->trip), ['place' => 'Heading back', 'sms' => '1'])->assertSessionHas('error');
        $this->assertSame(2, TripCheckin::count());
    }

    public function test_only_the_author_or_the_leader_removes_a_check_in(): void
    {
        $this->actingAs($this->leader)->post(route('trips.checkins.store', $this->trip), ['place' => 'Left Dhaka']);
        $checkin = TripCheckin::firstOrFail();

        $this->actingAs($this->asha)->delete(route('trips.checkins.destroy', [$this->trip, $checkin]))->assertForbidden();
        $this->actingAs($this->leader)->delete(route('trips.checkins.destroy', [$this->trip, $checkin]))->assertRedirect();
        $this->assertModelMissing($checkin);
    }
}
