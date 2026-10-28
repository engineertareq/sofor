<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/** For tests that sign up with an email: read the emailed code and type it in. Needs Notification::fake(). */
trait ConfirmsEmail
{
    /** The code in the latest "confirm your email" message to this address. */
    protected function emailedCode(string $email): string
    {
        $user = User::where('email', $email)->firstOrFail();
        $code = Notification::sent($user, VerifyEmailAddress::class)->last()?->code;

        $this->assertNotNull($code, "No code was emailed to {$email}.");

        return $code;
    }

    /** Type the emailed code in, as the traveller who is signed in. */
    protected function confirmEmail(string $email): TestResponse
    {
        return $this->post(route('verification.email.verify'), ['email_code' => $this->emailedCode($email)]);
    }
}
