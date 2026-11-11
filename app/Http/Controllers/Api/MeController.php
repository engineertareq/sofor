<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\DeviceToken;
use App\Models\VerificationCode;
use App\Services\AccountDeletion;
use App\Services\AvatarService;
use App\Services\ContactVerification;
use App\Services\EmailCodes;
use App\Services\PhoneCodes;
use App\Services\TripInvitationService;
use App\Support\Api\Present;
use App\Support\CodeCheck;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The signed in traveller's own account: profile, photo, password, the codes
 * that prove an email or phone, the phone alerts are pushed to, and deleting
 * the account.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => Present::account($request->user())]);
    }

    public function update(ProfileUpdateRequest $request, ContactVerification $verification): JsonResponse
    {
        $user = $request->user();
        $result = $verification->saveProfile($user, $request->validated());

        return response()->json([
            'user' => Present::account($user->refresh()),
            'message' => $result['problems'][0] ?? ($user->hasUnverifiedContact() && ($result['email_changed'] || $result['phone_changed'])
                ? __('Profile updated. We sent a code to confirm the change.')
                : __('Profile updated.')),
        ]);
    }

    /** The language the app is shown in also decides the language of emails and alerts. */
    public function locale(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(SetLocale::LOCALES))]]);

        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['user' => Present::account($request->user())]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Whoever knew the old password is out: every other phone and browser.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return response()->json(['message' => __('Password changed. Your other devices have been signed out.')]);
    }

    public function avatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.AvatarService::MAX_KILOBYTES, 'dimensions:min_width=64,min_height=64'],
        ], [
            'avatar.required' => __('Choose a photo to upload.'),
            'avatar.uploaded' => __('That photo could not be uploaded. Try one under 8 MB.'),
            'avatar.image' => __('Use a JPG, PNG or WebP photo.'),
            'avatar.mimes' => __('Use a JPG, PNG or WebP photo.'),
            'avatar.max' => __('Keep the photo under 8 MB.'),
            'avatar.dimensions' => __('That photo is too small. Use one at least 64 pixels across.'),
        ]);

        $user = $avatars->replace($request->user(), $request->file('avatar'));

        return response()->json(['user' => Present::account($user), 'message' => __('Profile photo updated.')]);
    }

    public function removeAvatar(Request $request, AvatarService $avatars): JsonResponse
    {
        $avatars->remove($request->user());

        return response()->json(['user' => Present::account($request->user()->refresh()), 'message' => __('Profile photo removed.')]);
    }

    /* ------------------------------------------------------------------
       Proving the email or phone with a six digit code
    ------------------------------------------------------------------ */

    public function sendEmailCode(Request $request, ContactVerification $verification): JsonResponse
    {
        $user = $request->user();
        $verification->sendEmailCode($user, 'code');

        return response()->json([
            'message' => __('We emailed a 6 digit code to :email.', ['email' => $user->email]),
            'cooldown' => $verification->emailCooldown($user),
        ]);
    }

    public function verifyEmail(Request $request, EmailCodes $codes, TripInvitationService $invitations): JsonResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => __('Type the 6 digit code from the email.')]);

        if ($user->email !== null && ! $user->hasVerifiedEmail()) {
            $result = $codes->verify(VerificationCode::PURPOSE_EMAIL, $user->email, (string) $request->input('code'));

            if ($result !== CodeCheck::Valid) {
                throw ValidationException::withMessages(['code' => $result->message(emailed: true)]);
            }

            $user->markEmailAsVerified();
            $invitations->claimPendingFor($user->refresh());
        }

        return response()->json(['user' => Present::account($user), 'message' => __('Your email is confirmed.')]);
    }

    public function sendPhoneCode(Request $request, ContactVerification $verification): JsonResponse
    {
        $user = $request->user();
        $verification->sendPhoneCode($user);

        return response()->json([
            'message' => __('We texted a 6 digit code to :phone.', ['phone' => Phone::pretty($user->phone)]),
            'cooldown' => $verification->phoneCooldown($user),
        ]);
    }

    public function verifyPhone(Request $request, PhoneCodes $codes, TripInvitationService $invitations): JsonResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => __('Type the 6 digit code from the text message.')]);

        if ($user->phone !== null && ! $user->hasVerifiedPhone()) {
            $result = $codes->verify(VerificationCode::PURPOSE_PHONE, $user->phone, (string) $request->input('code'));

            if ($result !== CodeCheck::Valid) {
                throw ValidationException::withMessages(['code' => $result->message()]);
            }

            $user->markPhoneAsVerified();
            $invitations->claimPendingFor($user->refresh());
        }

        return response()->json(['user' => Present::account($user), 'message' => __('Your phone number is confirmed.')]);
    }

    /** Fix a typo in an address that still waits for its code. */
    public function correctContact(Request $request, ContactVerification $verification): JsonResponse
    {
        $user = $request->user();
        $type = $verification->correctUnverified($request);

        $type === 'email' ? $verification->sendEmailCode($user, 'email') : $verification->sendPhoneCode($user, 'phone');

        return response()->json([
            'user' => Present::account($user),
            'message' => $type === 'email'
                ? __('Updated. We emailed a code to :email.', ['email' => $user->email])
                : __('Updated. We texted a code to :phone.', ['phone' => Phone::pretty($user->phone)]),
        ]);
    }

    /* ------------------------------------------------------------------
       Push alerts
    ------------------------------------------------------------------ */

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        DeviceToken::register($request->user(), $data['token'], $data['app_version'] ?? null);

        return response()->json(['message' => 'ok']);
    }

    public function forgetDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        $request->user()->devices()->where('token_hash', DeviceToken::hash($data['token']))->delete();

        return response()->json(['message' => 'ok']);
    }

    /* ------------------------------------------------------------------
       Deleting the account
    ------------------------------------------------------------------ */

    public function destroy(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => __('That password is not right.'),
        ]);

        $user = $request->user();

        if (($blockers = $deletion->blockers($user)) !== []) {
            throw ValidationException::withMessages(['account' => $blockers]);
        }

        $deletion->delete($user);

        return response()->json(['message' => __('Your account has been deleted.')]);
    }
}
