<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\PhoneCodes;
use App\Support\CodeCheck;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Forgotten passwords. An email gets a reset link (Laravel's password broker),
 * a phone gets a texted code. The answers never say whether an account uses
 * the address, so the form cannot be used to find out who is on Sofor.
 */
class PasswordResetController extends Controller
{
    private const SESSION_PHONE = 'password_reset_phone';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, PhoneCodes $codes): RedirectResponse
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

            $request->session()->put(self::SESSION_PHONE, $phone);

            return redirect()->route('password.phone')
                ->with('status', __('If an account uses :phone, a 6 digit code is on its way to it.', ['phone' => Phone::pretty($phone)]));
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

        // Sent, or no such account: the answer is the same either way.
        return back()->with('status', __('If an account uses :email, a link to reset the password is on its way. It works for 60 minutes.', ['email' => $email]));
    }

    /* ------------------------------------------------------------------
       By email: the link from the message
    ------------------------------------------------------------------ */

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            // Opening the link proved the inbox is theirs.
            $this->changePassword($user, $password, ['email_verified_at' => $user->email_verified_at ?? now()]);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('This reset link is invalid or has expired. Ask for a new one.')]);
        }

        return redirect()->route('login')->with('status', __('Password changed. Log in with your new password.'));
    }

    /* ------------------------------------------------------------------
       By phone: a texted code
    ------------------------------------------------------------------ */

    public function phone(Request $request, PhoneCodes $codes): View|RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_PHONE);

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password-phone', [
            'phone' => $phone,
            'cooldown' => $codes->cooldown(VerificationCode::PURPOSE_PASSWORD_RESET, $phone),
        ]);
    }

    public function resendPhone(Request $request, PhoneCodes $codes): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_PHONE);

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user !== null) {
            $codes->send(VerificationCode::PURPOSE_PASSWORD_RESET, $phone, $user);
        }

        return back()->with('status', __('If an account uses :phone, a new code is on its way.', ['phone' => Phone::pretty($phone)]));
    }

    public function updatePhone(Request $request, PhoneCodes $codes): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_PHONE);

        if ($phone === null) {
            return redirect()->route('password.request');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], ['code.required' => __('Type the 6 digit code from the text message.')]);

        $user = User::query()->where('phone', $phone)->first();

        // No account gets the same answer as a wrong code.
        $result = $user !== null
            ? $codes->verify(VerificationCode::PURPOSE_PASSWORD_RESET, $phone, (string) $request->input('code'))
            : CodeCheck::Wrong;

        if ($result !== CodeCheck::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        // Getting the text proved the phone is theirs.
        $this->changePassword($user, (string) $request->input('password'), ['phone_verified_at' => $user->phone_verified_at ?? now()]);
        $request->session()->forget(self::SESSION_PHONE);

        return redirect()->route('login')->with('status', __('Password changed. Log in with your new password.'));
    }

    /**
     * Set the new password and sign the account out everywhere else, so
     * whoever might have known the old one is shut out.
     *
     * @param  array<string, mixed>  $extra
     */
    private function changePassword(User $user, string $password, array $extra = []): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)] + $extra)->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
