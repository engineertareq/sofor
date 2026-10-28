<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening on every web response.
 *
 * - Framing: only Sofor may frame Sofor, so another site cannot lay the
 *   settle up or admin buttons under an invisible click (clickjacking).
 * - The CSP is deliberately narrow: framing, <base> and plugins. A script
 *   policy would break Alpine (which evaluates expressions) and bKash's
 *   checkout script, so scripts are covered by output escaping instead.
 * - HSTS only over HTTPS, so local development on http keeps working.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
            // Links out (sponsors, bKash) learn the site, never the page or its tokens.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Location only for Sofor's own pages: the check-in can attach where you are.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self), interest-cohort=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
