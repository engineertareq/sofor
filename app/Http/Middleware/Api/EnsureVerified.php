<?php

namespace App\Http\Middleware\Api;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Like EnsureContactIsVerified, with a code the app answers by opening its verify screen. */
class EnsureVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->hasVerifiedContact()) {
            throw new ApiException(403, 'contact_unverified', __('Confirm your email or phone number to continue.'));
        }

        return $next($request);
    }
}
