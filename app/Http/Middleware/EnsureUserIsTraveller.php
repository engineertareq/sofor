<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The traveller app is for travellers. Admin accounts work from the console,
 * the same way travellers are kept out of it by EnsureUserIsAdmin.
 */
class EnsureUserIsTraveller
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'Admin accounts use the Sofor console.');
            }

            return redirect()
                ->route('admin.dashboard')
                ->with('status', __('Admin accounts work from the console. Trips are open from Admin -> Trips.'));
        }

        return $next($request);
    }
}
