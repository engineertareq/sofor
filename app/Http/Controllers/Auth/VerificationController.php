<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesPendingInvites;
use App\Http\Controllers\Controller;
use App\Models\VerificationCode;
use App\Services\ContactVerification;
use App\Services\EmailCodes;
use App\Services\PhoneCodes;
use App\Services\TripInvitationService;
use App\Support\CodeCheck;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * /verify: confirming the email or phone on an account with a six digit code
 * (emailed or texted). One verified contact unlocks the traveller app; the
 * page also handles the other one later, and fixing a typo in an unverified
 * address.
 */
class VerificationController extends Controller
{
    use HandlesPendingInvites;

    public function notice(Request $request, ContactVerification $verification): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasUnverifiedContact()) {
            return redirect()->to($user->homeUrl());
        }

        return view('auth.verify', [
            'user' => $user,
            'emailPending' => $user->email !== null && ! $user->hasVerifiedEmail(),
            'phonePending' => $user->phone !== null && ! $user->hasVerifiedPhone(),
            'emailCooldown' => $verification->emailCooldown($user),
            'phoneCooldown' => $verification->phoneCooldown($user),
            'emailCodeSent' => $verification->emailCodeOutstanding($user),
            'codeSent' => $verification->phoneCodeOutstanding($user),
            // Somebody with one contact verified is only finishing the other.
            'canSkip' => $user->hasVerifiedContact(),
        ]);
    }

    public function sendEmailCode(Request $request, ContactVerification $verification): RedirectResponse
    {
        $user = $request->user();
        $verification->sendEmailCode($user, 'email_code');

        return back()->with('status', __('We emailed a 6 digit code to :email.', ['email' => $user->email]));
    }

    public function verifyEmail(Request $request, EmailCodes $codes, TripInvitationService $invitations): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['email_code' => ['required', 'string', 'max:20']], ['email_code.required' => __('Type the 6 digit code from the email.')]);

        if ($user->email === null || $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $result = $codes->verify(VerificationCode::PURPOSE_EMAIL, $user->email, (string) $request->input('email_code'));

        if ($result !== CodeCheck::Valid) {
            throw ValidationException::withMessages(['email_code' => $result->message(emailed: true)]);
        }

        $user->markEmailAsVerified();

        return $this->finishVerification($request, $invitations, $user->refresh(), __('Your email is confirmed.'));
    }

    public function sendPhoneCode(Request $request, ContactVerification $verification): RedirectResponse
    {
        $user = $request->user();
        $verification->sendPhoneCode($user);

        return back()->with('status', __('We texted a 6 digit code to :phone.', ['phone' => Phone::pretty($user->phone)]));
    }

    public function verifyPhone(Request $request, PhoneCodes $codes, TripInvitationService $invitations): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => __('Type the 6 digit code from the text message.')]);

        if ($user->phone === null || $user->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        $result = $codes->verify(VerificationCode::PURPOSE_PHONE, $user->phone, (string) $request->input('code'));

        if ($result !== CodeCheck::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        $user->markPhoneAsVerified();

        return $this->finishVerification($request, $invitations, $user->refresh(), __('Your phone number is confirmed.'));
    }

    /**
     * Fix a typo in an address that still waits for verification. A verified
     * one is changed from the profile instead.
     */
    public function updateContact(Request $request, ContactVerification $verification): RedirectResponse
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

        try {
            $type === 'email' ? $verification->sendEmailCode($user) : $verification->sendPhoneCode($user);
        } catch (ValidationException $e) {
            return redirect()->route('verification.notice')->with('error', __('Updated, but :first', ['first' => lcfirst((string) collect($e->errors())->flatten()->first())]));
        }

        return redirect()->route('verification.notice')->with('status', $type === 'email' ? __('Updated. We emailed a code to :email.', ['email' => $user->email]) : __('Updated. We texted a code to :phone.', ['phone' => Phone::pretty($user->phone)]));
    }
}
