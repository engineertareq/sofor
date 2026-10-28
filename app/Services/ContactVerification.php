<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Validation\ValidationException;

/**
 * Sends what an account needs to prove its contacts: a six digit code to the
 * email address (EmailCodes) and one to the phone (PhoneCodes). Both go out
 * in the same request, so a mail or SMS problem is reported back to the
 * person rather than failing the page.
 */
class ContactVerification
{
    public function __construct(
        private readonly PhoneCodes $phoneCodes,
        private readonly EmailCodes $emailCodes,
    ) {}

    /** @throws ValidationException */
    public function sendEmailCode(User $user, string $field = 'email'): void
    {
        if ($user->email === null || $user->hasVerifiedEmail()) {
            return;
        }

        $this->emailCodes->send(VerificationCode::PURPOSE_EMAIL, $user->email, $user, $field);
    }

    /** @throws ValidationException */
    public function sendPhoneCode(User $user, string $field = 'code'): void
    {
        if ($user->phone === null || $user->hasVerifiedPhone()) {
            return;
        }

        $this->phoneCodes->send(VerificationCode::PURPOSE_PHONE, $user->phone, $user, $field);
    }

    /**
     * Send everything still unverified, straight after sign up or a contact
     * change. Returns what went wrong, if anything, instead of throwing.
     *
     * @return list<string>
     */
    public function sendPending(User $user): array
    {
        $problems = [];

        foreach ([fn () => $this->sendEmailCode($user), fn () => $this->sendPhoneCode($user)] as $send) {
            try {
                $send();
            } catch (ValidationException $e) {
                array_push($problems, ...array_values($e->validator->errors()->all()));
            }
        }

        return $problems;
    }

    public function emailCooldown(User $user): int
    {
        return $user->email !== null ? $this->emailCodes->cooldown(VerificationCode::PURPOSE_EMAIL, $user->email) : 0;
    }

    public function phoneCooldown(User $user): int
    {
        return $user->phone !== null ? $this->phoneCodes->cooldown(VerificationCode::PURPOSE_PHONE, $user->phone) : 0;
    }

    /** Whether a code went to the inbox recently enough to still be typed in. */
    public function emailCodeOutstanding(User $user): bool
    {
        return $user->email !== null && $this->emailCodes->outstanding(VerificationCode::PURPOSE_EMAIL, $user->email);
    }

    /** Whether a code went to the phone recently enough to still be typed in. */
    public function phoneCodeOutstanding(User $user): bool
    {
        return $user->phone !== null && $this->phoneCodes->outstanding(VerificationCode::PURPOSE_PHONE, $user->phone);
    }
}
