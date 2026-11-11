<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Save the profile form, from the website or the app. An email or phone
     * that changed has to be proven again, so its code goes out at once.
     *
     * @param  array<string, mixed>  $data  validated by ProfileUpdateRequest
     * @return array{email_changed: bool, phone_changed: bool, problems: list<string>}
     */
    public function saveProfile(User $user, array $data): array
    {
        $emailChanged = $user->email !== ($data['email'] ?? null);
        $phoneChanged = $user->phone !== ($data['phone'] ?? null);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        if (array_key_exists('profile_public', $data)) {
            $user->profile_public = (bool) $data['profile_public'];
        }

        $user->save();

        return [
            'email_changed' => $emailChanged,
            'phone_changed' => $phoneChanged,
            'problems' => $emailChanged || $phoneChanged ? $this->sendPending($user) : [],
        ];
    }

    /**
     * Fix a typo in an address that still waits for verification (`type` is
     * email or phone). A verified one is changed from the profile instead.
     * The new code is the caller's to send.
     *
     * @return string which contact was changed: "email" or "phone"
     */
    public function correctUnverified(Request $request): string
    {
        $user = $request->user();
        $type = $request->input('type') === 'phone' ? 'phone' : 'email';

        if ($type === 'phone') {
            $request->merge(['phone' => Phone::normalize((string) $request->input('phone'))]);
        } else {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $request->validate([
            'email' => [Rule::requiredIf($type === 'email'), 'nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => [
                Rule::requiredIf($type === 'phone'), 'nullable', 'string', 'max:24', Rule::unique('users', 'phone')->ignore($user->id),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && ! Phone::isValid((string) $value)) {
                        $fail(__('Enter a valid Bangladeshi mobile number, for example 01712345678.'));
                    }
                },
            ],
        ], [
            'email.unique' => __('Another account already uses this email.'),
            'phone.unique' => __('Another account already uses this phone number.'),
        ]);

        $verified = $type === 'email' ? $user->hasVerifiedEmail() : $user->hasVerifiedPhone();
        abort_if($verified, 403, 'Change a confirmed contact from your profile.');

        $user->forceFill($type === 'email'
            ? ['email' => $request->input('email'), 'email_verified_at' => null]
            : ['phone' => $request->input('phone'), 'phone_verified_at' => null])->save();

        return $type;
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
