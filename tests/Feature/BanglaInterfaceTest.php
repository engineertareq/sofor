<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripInvitationService;
use App\Support\Bangla;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sofor in English or Bangla. */
class BanglaInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_traveller_switches_to_bangla_and_it_sticks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('<html lang="en">', false)->assertSee('My trips');

        $this->from(route('dashboard'))->post(route('locale'), ['locale' => 'bn'])->assertRedirect(route('dashboard'));
        $this->assertSame('bn', $user->refresh()->locale);

        $this->get(route('dashboard'))
            ->assertSee('<html lang="bn">', false)
            ->assertSee('আমার ট্রিপ')
            ->assertSee('Hind+Siliguri', false);

        $this->post(route('locale'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_bangla_is_the_default_and_a_choice_of_english_sticks(): void
    {
        config(['app.interface_locale' => 'bn']);   // the real default; the suite itself reads in English

        // Whatever the browser is set to, Sofor opens in Bangla.
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get(route('home'))
            ->assertSee('<html lang="bn">', false)
            ->assertSee('<meta property="og:locale" content="bn_BD">', false);

        // Accounts that never picked a language read Bangla too, and get their emails in it.
        $traveller = User::factory()->create(['locale' => null]);
        $this->assertSame('bn', $traveller->preferredLocale());
        $this->actingAs($traveller)->get(route('dashboard'))->assertSee('<html lang="bn">', false);

        // The console is English only, so admin accounts start in English.
        $admin = User::factory()->admin()->create(['locale' => null]);
        $this->assertSame('en', $admin->preferredLocale());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('<html lang="en">', false);

        // A visitor who picks English keeps it for the visit, and an account keeps it for good.
        $this->flushSession();
        auth()->logout();
        $this->post(route('locale'), ['locale' => 'en']);
        $this->get(route('login'))->assertSee('<html lang="en">', false);
        $this->assertSame('en', User::factory()->make(['locale' => 'en'])->preferredLocale());

        $this->post(route('locale'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_the_trip_page_reads_in_bangla_and_the_pdf_stays_english(): void
    {
        $leader = User::factory()->create(['locale' => 'bn']);
        $trip = Trip::factory()->create(['user_id' => $leader->id]);
        app(TripInvitationService::class)->ensureOwnerMember($trip, $leader);

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cottage', 'amount' => 3000, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->value('id'), 'split_type' => 'equal',
        ]);

        $this->get(route('trips.show', $trip))
            ->assertOk()
            ->assertSee('খরচের খাতা')        // Cost ledger
            ->assertSee('মোট খরচ')           // Total spent
            ->assertSee('থাকা');              // the "Stay" category

        $this->get(route('trips.summary', $trip))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bangla_pages_use_bangla_digits_but_leave_code_and_references_alone(): void
    {
        // Usernames and codes keep their digits: they are typed and copied exactly.
        $this->assertSame('<p>@tanvir1 · NG7731 · ৳১,২৩৪ · ২০২৬</p>', Bangla::html('<p>@tanvir1 · NG7731 · ৳1,234 · 2026</p>'));

        $html = '<div x-data="{ n: () => this.members.length + 1, max: 40 }" title="a > b 5">Total 1234 '
            .'<span class="font-mono">NG7731</span> <span translate="no">01712345678</span></div>'
            .'<script>if (a<b) x = 2;</script><p>9</p><textarea>1 < 2</textarea> &#039;5';

        $this->assertSame(
            '<div x-data="{ n: () => this.members.length + 1, max: 40 }" title="a > b 5">Total ১২৩৪ '
            .'<span class="font-mono">NG7731</span> <span translate="no">01712345678</span></div>'
            .'<script>if (a<b) x = 2;</script><p>৯</p><textarea>1 < 2</textarea> &#039;৫',
            Bangla::html($html),
        );

        $this->actingAs(User::factory()->create(['locale' => 'bn']))
            ->get(route('trips.create'))
            ->assertOk()
            ->assertSee('this.members.length < 40', false)   // Alpine code keeps its digits
            ->assertDontSee('this.members.length < ৪০', false);
    }

    public function test_bangla_money_uses_bangla_digits_and_lakh_grouping(): void
    {
        app()->setLocale('bn');

        $this->assertSame('৳১২,৩৪,৫৬৭', Money::format(1234567));
        $this->assertSame('৳১,৫০০.৫০', Money::format(1500.5));

        app()->setLocale('en');

        $this->assertSame('৳1,234,567', Money::format(1234567));
    }

    public function test_case_endings_join_bangla_names_and_stay_hyphenated_after_english_ones(): void
    {
        app()->setLocale('bn');

        $this->assertSame('বাংলাদেশের এক্সপ্লোরার', __('Explorers of :place', ['place' => 'বাংলাদেশ']));
        $this->assertSame('ঢাকার এক্সপ্লোরার', __('Explorers of :place', ['place' => 'ঢাকা']));
        $this->assertSame('Sajek-এর এক্সপ্লোরার', __('Explorers of :place', ['place' => 'Sajek']));
        $this->assertSame('ঢাকায়', 'ঢাকা'.Bangla::ending('ঢাকা', 'এ'));
        $this->assertSame('রাঙামাটিতে', 'রাঙামাটি'.Bangla::ending('রাঙামাটি', 'এ'));
        $this->assertNull(Bangla::ending('৳৫০০', 'এর'));
    }

    public function test_each_traveller_is_notified_in_their_own_language(): void
    {
        $leader = User::factory()->create(['locale' => 'en']);
        $friend = User::factory()->create(['locale' => 'bn']);
        $trip = Trip::factory()->create(['user_id' => $leader->id, 'title' => 'Sajek']);
        $invitations = app(TripInvitationService::class);
        $invitations->ensureOwnerMember($trip, $leader);
        $invitations->joinViaCode($trip, $friend);

        $this->actingAs($friend)->post(route('trips.expenses.store', $trip), [
            'title' => 'Cottage', 'amount' => 3000, 'category' => 'accommodation', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->where('user_id', $friend->id)->value('id'), 'split_type' => 'equal',
        ])->assertRedirect();

        $this->assertContains('New cost in Sajek', $leader->notifications()->get()->pluck('data.title'));

        $this->actingAs($leader)->post(route('trips.expenses.store', $trip), [
            'title' => 'Bus', 'amount' => 800, 'category' => 'transport', 'spent_on' => now()->toDateString(),
            'trip_member_id' => $trip->members()->where('user_id', $leader->id)->value('id'), 'split_type' => 'equal',
        ])->assertRedirect();

        $this->assertContains('Sajek-এ নতুন খরচ', $friend->notifications()->get()->pluck('data.title'));

        // The timeline follows whoever reads it.
        $this->actingAs($leader)->get(route('trips.show', $trip))->assertSee('logged Cottage for');
        $this->actingAs($friend)->get(route('trips.show', $trip))
            ->assertSee('Cottage লিখেছেন')
            ->assertDontSee('@js(', false)     // Blade leaves @js uncompiled inside <x-...> tags
            ->assertDontSee('।', false);       // plain full stops, no dari
    }
}
