<?php

namespace App\Http\Middleware\Api;

use App\Http\Middleware\SetLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Messages come back in the language the app is showing (Accept-Language: bn
 * or en). Numbers in JSON are always plain digits: the app formats them.
 */
class UseRequestLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $asked = strtolower(substr(trim((string) $request->header('Accept-Language')), 0, 2));

        $locale = array_key_exists($asked, SetLocale::LOCALES)
            ? $asked
            : SetLocale::defaultFor(null);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
