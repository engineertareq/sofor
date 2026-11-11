<?php

namespace App\Http\Middleware\Api;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app is for travellers. Admin accounts work from the web console only,
 * and a paused account is signed out of the app at its next request.
 */
class TravellersOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            throw new ApiException(401, 'account_paused', __('This account has been paused. Please contact Sofor support.'));
        }

        if ($user?->isAdmin()) {
            throw new ApiException(403, 'admin_account', __('Administrator accounts use the web console at sofor.io, not the app.'));
        }

        return $next($request);
    }
}
