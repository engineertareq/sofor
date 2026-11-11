<?php

namespace App\Services;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Checks an email or phone and password, the same way for the website and the
 * app: rate limited, paused accounts refused, and only administrators let in
 * while Sofor is down for maintenance.
 */
class SignIn
{
    public function __construct(private readonly MaintenanceMode $maintenance) {}

    public function attempt(LoginRequest $request): User
    {
        $request->ensureIsNotRateLimited();

        $validated = $request->validated();
        $login = $validated['login'];

        $user = Phone::looksLikePhone($login)
            ? User::where('phone', Phone::normalize($login))->first()
            : User::where('email', strtolower($login))->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('We could not match those details. Check your email or phone and password.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => __('This account has been paused. Please contact Sofor support.'),
            ]);
        }

        // The sign in screen stays open during maintenance so administrators can
        // get in; everybody else is asked to come back later.
        if (! $user->isAdmin() && $this->maintenance->active()) {
            throw ValidationException::withMessages([
                'login' => __('Sofor is down for maintenance, so only administrators can sign in right now. Please try again soon.'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        return $user;
    }
}
