<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pausing an account in the console has to take effect at once. Login
 * refuses paused accounts, but somebody already signed in - or carrying a
 * "keep me signed in" cookie - would otherwise carry on as if nothing happened.
 */
class SignOutPausedAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401, 'This account has been paused.');
        }

        return redirect()->route('login')->with('error', __('This account has been paused. Please contact Sofor support.'));
    }
}
