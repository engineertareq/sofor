<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Services\EmailCodes;
use App\Services\PhoneCodes;
use App\Services\TripInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\ConfirmsEmail;
use Tests\TestCase;

/**
 * Sign up verification (an emailed or texted code), the gate in front of the
 * traveller app, and password resets by email or by text.
 * Texts go through a faked sms.net.bd, so the real client runs end to end.
 */
class VerificationTest extends TestCase
{
    use ConfirmsEmail, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.driver' => 'smsnetbd',
            'services.sms.smsnetbd.url' => 'https://api.sms.test',
            'services.sms.smsnetbd.key' => 'test-key',
        ]);

        $this->fakeGateway();

        // The first account becomes the admin; these tests are about travellers.
        User::factory()->admin()->create();
    }

    /** What the fake gateway answers with: 0 is success, anything else one of sms.net.bd's error codes. */
    private int $gatewayError = 0;

    private function fakeGateway(): void
    {
        Http::fake(['api.sms.test/*' => fn () => Http::response([
            'error' => $this->gatewayError,
            'msg' => $this->gatewayError === 0 ? 'Request successfully submitted' : 'Insufficient balance',
            'data' => ['request_id' => 77],
        ])]);
    }

    /** The code in the most recent text message. */
    private function lastCode(): string
    {
        $sent = Http::recorded()->last();
        $this->assertNotNull($sent, 'No text message was sent.');
        preg_match('/\b(\d{6})\b/', $sent[0]['msg'], $match);

        return $match[1];
    }

    /** @param  array<string, mixed>  $overrides */
    private function register(array $overrides = [])
    {
        return $this->post(route('register.store'), $overrides + [
            'name' => 'Nusrat Jahan',
            'username' => 'nusrat',
            'contact_type' => 'email',
            'email' => 'nusrat@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);
    }

    /* ------------------------------------------------------------------
       Email
    ------------------------------------------------------------------ */

    public function test_signing_up_with_an_email_sends_a_branded_code(): void
    {
        Notification::fake();

        $this->register()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'nusrat@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedContact());
        $code = $this->emailedCode('nusrat@example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        Notification::assertSentTo($user, VerifyEmailAddress::class, function (VerifyEmailAddress $notification) use ($user, $code) {
            $mail = $notification->toMail($user);
            $html = (string) $mail->render();

            // The code shows in the subject and the email; the header is the logo, and nothing is a link to click.
            return $mail->subject === "{$code} is your Sofor code"
                && str_contains($html, $code)
                && str_contains($html, 'images/email/sofor-logo.png')
                && ! str_contains($html, '/verify/');
        });

        // Only a keyed hash is stored, never the code.
        $stored = VerificationCode::where('purpose', VerificationCode::PURPOSE_EMAIL)->firstOrFail();
        $this->assertSame('nusrat@example.com', $stored->destination);
        $this->assertStringNotContainsString($code, $stored->code_hash);

        $this->get(route('verification.notice'))->assertOk()
            ->assertSee('Enter the code we emailed')
            ->assertSee('nusrat@example.com')
            ->assertSee('one-time-code', false);
    }

    public function test_the_traveller_app_stays_shut_until_a_contact_is_confirmed(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('trips.index'))->assertRedirect(route('verification.notice'));
        $this->getJson(route('trips.past-travellers', ['q' => 'a']))->assertForbidden();

        // The way out stays open.
        $this->get(route('verification.notice'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('home'));
    }

    public function test_the_emailed_code_confirms_the_email_and_returns_to_where_they_were_going(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'rafi@example.com', 'phone' => null]);

        $this->actingAs($user)->get(route('trips.index'))->assertRedirect(route('verification.notice'));
        $this->post(route('verification.send'))->assertSessionHasNoErrors();

        // Spaces from copying the code out of the email do not matter.
        $code = $this->emailedCode('rafi@example.com');
        $this->post(route('verification.email.verify'), ['email_code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertRedirect(route('trips.index'));

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertFalse($user->hasVerifiedPhone());
    }

    public function test_a_wrong_expired_or_outdated_email_code_confirms_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'rafi@example.com', 'phone' => null]);
        $this->actingAs($user)->post(route('verification.send'));
        $code = $this->emailedCode('rafi@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post(route('verification.email.verify'), ['email_code' => $wrong])
            ->assertSessionHasErrors(['email_code' => 'That code is not right. Check the email and try again.']);

        $this->travel(EmailCodes::TTL_MINUTES + 1)->minutes();
        $this->post(route('verification.email.verify'), ['email_code' => $code])
            ->assertSessionHasErrors(['email_code' => 'That code has expired. Ask for a new one below.']);

        // A code sent to an address the account no longer has.
        $this->post(route('verification.send'));
        $old = $this->emailedCode('rafi@example.com');
        $user->forceFill(['email' => 'new@example.com'])->save();
        $this->post(route('verification.email.verify'), ['email_code' => $old])->assertSessionHasErrors('email_code');

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_five_wrong_tries_use_up_an_email_code(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'rafi@example.com', 'phone' => null]);
        $this->actingAs($user)->post(route('verification.send'));
        $code = $this->emailedCode('rafi@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, EmailCodes::MAX_ATTEMPTS) as $try) {
            $this->post(route('verification.email.verify'), ['email_code' => $wrong])->assertSessionHasErrors('email_code');
        }

        $this->post(route('verification.email.verify'), ['email_code' => $code])
            ->assertSessionHasErrors(['email_code' => 'Too many wrong tries for that code. Ask for a new one below.']);
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_the_email_can_be_sent_again_but_not_every_second(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['phone' => null]);

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHasNoErrors();
        $this->post(route('verification.send'))->assertSessionHasErrors('email_code');
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 2);

        // Five an hour at most.
        foreach (range(3, EmailCodes::MAX_PER_HOUR) as $n) {
            $this->travel(61)->seconds();
            $this->post(route('verification.send'))->assertSessionHasNoErrors();
        }

        $this->travel(61)->seconds();
        $this->post(route('verification.send'))
            ->assertSessionHasErrors(['email_code' => 'Too many codes went to this email. Please try again in an hour.']);
    }

    public function test_a_mail_server_problem_never_breaks_sign_up(): void
    {
        // Nothing listens on port 1, so sending fails straight away.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);

        $this->register()
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error', 'We could not send the email just now. Please try again in a minute.');

        $this->assertAuthenticated();

        // Not sent, so it does not count against the limits.
        $this->assertSame(0, VerificationCode::where('purpose', VerificationCode::PURPOSE_EMAIL)->count());
    }

    public function test_a_typo_in_an_unconfirmed_email_can_be_fixed(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'nusart@exmaple.com', 'phone' => null]);

        $this->actingAs($user)
            ->put(route('verification.contact.update'), ['type' => 'email', 'email' => 'Nusrat@Example.com'])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame('nusrat@example.com', $user->refresh()->email);
        Notification::assertSentTo($user, VerifyEmailAddress::class);

        // A confirmed contact is changed from the profile instead.
        $confirmed = User::factory()->create();
        $this->actingAs($confirmed)->put(route('verification.contact.update'), ['type' => 'email', 'email' => 'x@example.com'])->assertForbidden();
    }

    /* ------------------------------------------------------------------
       Phone
    ------------------------------------------------------------------ */

    public function test_signing_up_with_a_phone_texts_a_code_that_confirms_it(): void
    {
        $this->register(['contact_type' => 'phone', 'email' => null, 'phone' => '01712345678'])
            ->assertRedirect(route('verification.notice'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.sms.test/sendsms'
            && $request['api_key'] === 'test-key'
            && $request['to'] === '8801712345678'
            && str_starts_with($request['msg'], 'Sofor: ')
            && strlen($request['msg']) <= 160);

        $this->get(route('verification.notice'))->assertOk()->assertSee('Enter the code we texted')->assertSee('one-time-code', false);

        // Only a keyed hash is stored, never the code.
        $code = $this->lastCode();
        $this->assertStringNotContainsString($code, (string) VerificationCode::first()->code_hash);

        $this->post(route('verification.phone.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertTrue(User::where('phone', '+8801712345678')->firstOrFail()->hasVerifiedPhone());
    }

    public function test_five_wrong_tries_use_up_a_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801711111111']);
        $this->actingAs($user)->post(route('verification.phone.send'))->assertSessionHasNoErrors();
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, PhoneCodes::MAX_ATTEMPTS) as $try) {
            $this->post(route('verification.phone.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Even the right code is refused now.
        $this->post(route('verification.phone.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Too many wrong tries for that code. Ask for a new one below.']);
        $this->assertFalse($user->refresh()->hasVerifiedPhone());
    }

    public function test_a_code_expires_and_only_the_newest_one_works(): void
    {
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801722222222']);
        $this->actingAs($user)->post(route('verification.phone.send'));
        $first = $this->lastCode();

        $this->travel(PhoneCodes::RESEND_SECONDS + 1)->seconds();
        $this->post(route('verification.phone.send'))->assertSessionHasNoErrors();
        $second = $this->lastCode();

        if ($first !== $second) {
            $this->post(route('verification.phone.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }

        $this->travel(PhoneCodes::TTL_MINUTES + 1)->minutes();
        $this->post(route('verification.phone.verify'), ['code' => $second])
            ->assertSessionHasErrors(['code' => 'That code has expired. Ask for a new one below.']);
    }

    public function test_texts_are_limited_to_one_a_minute_and_five_an_hour(): void
    {
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801733333333']);
        $this->actingAs($user);

        $this->post(route('verification.phone.send'))->assertSessionHasNoErrors();
        $this->post(route('verification.phone.send'))->assertSessionHasErrors('code');

        foreach (range(2, PhoneCodes::MAX_PER_HOUR) as $n) {
            $this->travel(PhoneCodes::RESEND_SECONDS + 1)->seconds();
            $this->post(route('verification.phone.send'))->assertSessionHasNoErrors();
        }

        $this->travel(PhoneCodes::RESEND_SECONDS + 1)->seconds();
        $this->post(route('verification.phone.send'))
            ->assertSessionHasErrors(['code' => 'Too many codes went to this number. Please try again in an hour.']);

        Http::assertSentCount(PhoneCodes::MAX_PER_HOUR);
    }

    public function test_a_gateway_refusal_is_explained_and_costs_nothing(): void
    {
        $this->gatewayError = 417;
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801744444444']);

        $this->actingAs($user)->post(route('verification.phone.send'))
            ->assertSessionHasErrors(['code' => 'We could not text +880 1744-444444 just now. Please try again in a minute.']);

        // Not sent, so it does not count against the limits.
        $this->assertSame(0, VerificationCode::count());
    }

    public function test_the_log_driver_never_calls_the_gateway(): void
    {
        config(['services.sms.driver' => 'log']);
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801755555555']);

        $this->actingAs($user)->post(route('verification.phone.send'))->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(1, VerificationCode::count());
    }

    /* ------------------------------------------------------------------
       Invites and profile changes
    ------------------------------------------------------------------ */

    public function test_signing_up_with_somebody_elses_email_wins_none_of_their_invites(): void
    {
        $leader = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $leader->id]);

        // An impostor signs up first with the invitee's address but cannot confirm it.
        $impostor = User::factory()->unverified()->create(['email' => 'friend@example.com', 'phone' => null]);

        app(TripInvitationService::class)->invite($trip, $leader, ['name' => 'Friend', 'email' => 'friend@example.com']);

        $row = TripMember::where('email', 'friend@example.com')->firstOrFail();
        $this->assertNull($row->user_id);
        $this->assertSame('invited', $row->status->value);

        app(TripInvitationService::class)->claimPendingFor($impostor);
        $this->assertNull($row->refresh()->user_id);
    }

    public function test_changing_the_email_in_the_profile_asks_to_confirm_the_new_one(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'phone' => '+8801766666666']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new@example.com',
            'phone' => '01766666666',
        ])->assertRedirect(route('verification.notice'));

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->hasVerifiedPhone());
        Notification::assertSentTo($user, VerifyEmailAddress::class);

        // The confirmed phone keeps the app open meanwhile.
        $this->get(route('verification.notice'))->assertOk()->assertSee('Skip for now');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_fully_confirmed_account_skips_the_verify_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('verification.notice'))->assertRedirect(route('dashboard'));
    }

    /* ------------------------------------------------------------------
       Password reset
    ------------------------------------------------------------------ */

    public function test_a_password_is_reset_from_an_emailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'rakib@example.com']);

        $this->get(route('login'))->assertSee(route('password.request'), false);

        $this->post(route('password.email'), ['login' => 'Rakib@Example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user, &$token) {
            $token = $notification->token;
            $mail = $notification->toMail($user);

            return $mail->subject === 'Reset your Sofor password' && str_contains($mail->actionUrl, '/reset-password/'.$token);
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Choose a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'rakib@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
        // Opening the link proved the inbox is theirs.
        $this->assertTrue($user->hasVerifiedEmail());

        // A used token is gone.
        $this->post(route('password.update'), ['token' => $token, 'email' => 'rakib@example.com', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_reset_form_never_says_whether_an_account_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['login' => 'nobody@example.com'])
            ->assertSessionHas('status', 'If an account uses nobody@example.com, a link to reset the password is on its way. It works for 60 minutes.');
        Notification::assertNothingSent();

        $this->post(route('password.email'), ['login' => '01799999999'])->assertRedirect(route('password.phone'));
        Http::assertNothingSent();

        // Any code is simply "not right".
        $this->post(route('password.phone.update'), ['code' => '123456', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors(['code' => 'That code is not right. Check the text message and try again.']);
    }

    public function test_a_password_is_reset_with_a_texted_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+8801777777777']);

        $this->post(route('password.email'), ['login' => '+880 1777-777777'])->assertRedirect(route('password.phone'));
        Http::assertSent(fn (HttpRequest $request) => $request['to'] === '8801777777777' && str_contains($request['msg'], 'password reset code'));

        $this->get(route('password.phone'))->assertOk()->assertSee('+880 1777-777777');

        $this->post(route('password.phone.update'), ['code' => '000000', 'password' => 'newpass123', 'password_confirmation' => 'newpass123']);
        $this->assertFalse(Hash::check('newpass123', $user->refresh()->password));

        $this->travel(PhoneCodes::RESEND_SECONDS + 1)->seconds();
        $this->post(route('password.phone.resend'))->assertSessionHas('status');

        $this->post(route('password.phone.update'), ['code' => $this->lastCode(), 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
        $this->assertTrue($user->hasVerifiedPhone());

        $this->post(route('login.store'), ['login' => '01777777777', 'password' => 'newpass123'])->assertRedirect(route('dashboard'));
    }
}
