<?php

namespace App\Http\Middleware\Api;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app says which build it is (X-Sofor-App: android/42). A build older
 * than the oldest one still supported is told to update before anything else.
 */
class CheckAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimum = (int) config('services.android.min_version_code', 0);

        if ($minimum > 0 && preg_match('#^android/(\d+)$#', (string) $request->header('X-Sofor-App'), $match) === 1 && (int) $match[1] < $minimum) {
            throw new ApiException(426, 'update_required', __('This version of Sofor is too old. Please update the app to continue.'));
        }

        return $next($request);
    }
}
