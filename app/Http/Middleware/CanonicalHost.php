<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every page has one address on the live site. In production a GET for
 * www.sofor.io/..., for sofor.io/public/... (the folder the host rewrites
 * into, which the old trailing-slash redirect leaked) or for a path with a
 * trailing slash is sent, in one 301, to the same page at APP_URL.
 *
 * The scheme is left to the server, which already sends http to https:
 * behind a proxy the app may not see https, and redirecting on it would loop.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production') || ! $request->isMethodSafe()) {
            return $next($request);
        }

        $root = rtrim((string) config('app.url'), '/');
        $host = parse_url($root, PHP_URL_HOST);
        $base = rtrim((string) parse_url($root, PHP_URL_PATH), '/');

        if (! is_string($host) || $host === '') {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $trimmed = $path === '/' ? '/' : rtrim($path, '/');

        $wrongHost = strcasecmp($request->getHost(), 'www.'.$host) === 0;
        $wrongBase = $request->getBaseUrl() !== $base && str_ends_with($request->getBaseUrl(), '/public');

        if (! $wrongHost && ! $wrongBase && $trimmed === $path) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect()->to($root.($trimmed === '/' ? '' : $trimmed).($query !== null ? '?'.$query : ''), 301);
    }
}
