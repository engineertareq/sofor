<?php

namespace App\Http\Middleware;

use App\Support\Bangla;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In Bangla, every number on a page is written with Bangla digits: counts,
 * dates, times and "2 hours ago" alike, not just the amounts Money formats.
 * Codes and phone numbers stay Latin (see Bangla::html).
 */
class LocalizeDigits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Bangla::active()
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $response->setContent(Bangla::html($content));
        }

        return $response;
    }
}
