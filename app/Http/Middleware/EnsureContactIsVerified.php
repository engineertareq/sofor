<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The traveller app needs one proven way to reach the account - a confirmed
 * email or phone - before it opens. Everybody else is sent to /verify, and
 * comes back to the page they asked for once they are done.
 */
class EnsureContactIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedContact()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Confirm your email or phone number to continue.');
        }

        return $request->isMethod('GET')
            ? redirect()->guest(route('verification.notice'))
            : redirect()->route('verification.notice');
    }
}
