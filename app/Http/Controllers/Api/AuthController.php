<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\ContactVerification;
use App\Services\PhoneCodes;
use App\Services\SignIn;
use App\Services\TripInvitationService;
use App\Support\Api\Present;
use App\Support\CodeCheck;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Signing in from the app. A phone holds one token, named after the device;
 * signing out deletes it. The checks are the website's own (SignIn,
 * RegisterRequest, the phone codes), so an account behaves the same in both.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request, ContactVerification $verification): JsonResponse
    {
        // The first account on a fresh install is the administrator, and that is set up on the website.
        if (User::query()->count() === 0) {
            throw new ApiException(403, 'not_ready', __('Sofor is not set up yet. Please try again later.'));
        }

        $data = $request->validated();

        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['contact_type'] === 'email' ? $data['email'] : null,
            'phone' => $data['contact_type'] === 'phone' ? $data['phone'] : null,
            'password' => $data['password'],
            'timezone' => config('app.timezone'),
            'last_seen_at' => now(),
        ]);

        $user->forceFill(['role' => 'user', 'is_active' => true, 'locale' => app()->getLocale()])->save();

        // A mail or SMS hiccup is not a failed sign up: the app's verify screen can ask again.
        $problems = $verification->sendPending($user);

        return $this->signedIn($request, $user, 201, $problems[0] ?? ($user->email !== null
            ? __('Welcome to Sofor, :user! One last step: we emailed a 6 digit code to :email.', ['user' => $user->name, 'email' => $user->email])
            : __('Welcome to Sofor, :user! One last step: we texted a 6 digit code to :phone.', ['user' => $user->name, 'phone' => Phone::pretty($user->phone)])));
    }

    public function login(LoginRequest $request, SignIn $signIn, TripInvitationService $invitations): JsonResponse
    {
        $user = $signIn->attempt($request);

        if ($user->isAdmin()) {
            throw new ApiException(403, 'admin_account', __('Administrator accounts use the web console at sofor.io, not the app.'));
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        // Trips this email or phone was invited to before the account existed.
        $invitations->claimPendingFor($user);

        return $this->signedIn($request, $user, 200, __('Welcome back, :user!', ['user' => $user->name]));
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->filled('fcm_token')) {
            DeviceToken::forget((string) $request->input('fcm_token'));
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('You have been signed out.')]);
    }

    /** Forgotten password: a texted code for a phone, an emailed link for an email. */
    public function forgotPassword(Request $request, PhoneCodes $codes): JsonResponse
    {
        $login = trim((string) $request->input('login'));
        $request->merge(['login' => $login]);
        $request->validate(['login' => ['required', 'string', 'max:255']], ['login.required' => __('Enter the email or phone number on your account.')]);

        if (Phone::looksLikePhone($login)) {
            if (! Phone::isValid($login)) {
                throw ValidationException::withMessages(['login' => __('Enter a valid Bangladeshi mobile number, for example 01712345678.')]);
            }

            $phone = (string) Phone::normalize($login);
            $user = User::query()->where('phone', $phone)->first();

            if ($user !== null) {
                $codes->send(VerificationCode::PURPOSE_PASSWORD_RESET, $phone, $user, 'login');
            }

            // Sent, or no such account: the answer is the same either way.
            return response()->json([
                'channel' => 'phone',
                'phone' => $phone,
                'message' => __('If an account uses :phone, a 6 digit code is on its way to it.', ['phone' => Phone::pretty($phone)]),
            ]);
        }

        $email = strtolower($login);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['login' => __('That does not look like an email address or a phone number.')]);
        }

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['login' => __('We could not send the email just now. Please try again in a minute.')]);
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['login' => __('A reset link was just sent. Please wait a minute before asking again.')]);
        }

        return response()->json([
            'channel' => 'email',
            'message' => __('If an account uses :email, a link to reset the password is on its way. It works for 60 minutes.', ['email' => $email]),
        ]);
    }

    /** The texted code and a new password. Every device is signed out. */
    public function resetPassword(Request $request, PhoneCodes $codes): JsonResponse
    {
        $request->merge(['phone' => Phone::normalize((string) $request->input('phone'))]);

        $request->validate([
            'phone' => ['required', 'string', 'max:24'],
            'code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], ['code.required' => __('Type the 6 digit code from the text message.')]);

        $phone = (string) $request->input('phone');
        $user = User::query()->where('phone', $phone)->first();

        // No account gets the same answer as a wrong code.
        $result = $user !== null
            ? $codes->verify(VerificationCode::PURPOSE_PASSWORD_RESET, $phone, (string) $request->input('code'))
            : CodeCheck::Wrong;

        if ($result !== CodeCheck::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        // Getting the text proved the phone is theirs.
        $user->forceFill([
            'password' => (string) $request->input('password'),
            'remember_token' => Str::random(60),
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ])->save();

        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return response()->json(['message' => __('Password changed. Log in with your new password.')]);
    }

    private function signedIn(Request $request, User $user, int $status, string $message): JsonResponse
    {
        $device = Str::limit(trim((string) $request->input('device_name')) ?: 'Android', 120, '');

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => Present::account($user),
            'message' => $message,
        ], $status);
    }
}
