<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public half of the branding settings: the uploaded logo and favicon files,
 * and a web app manifest whose icons follow the favicon.
 */
class BrandingController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function asset(string $file): StreamedResponse
    {
        $path = BrandingService::DIRECTORY.'/'.$file;
        $disk = Storage::disk(BrandingService::DISK);

        // Only images currently in use; replaced ones are deleted anyway.
        abort_unless($this->branding->serves($file) && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/png',
            // Every upload gets a new file name, so a copy can be kept for good.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function manifest(): JsonResponse
    {
        $icons = $this->branding->hasFavicon()
            ? [
                ['src' => $this->branding->faviconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $this->branding->faviconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ]
            : [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        return response()->json([
            'name' => __('Sofor - Split trips, map your travels'),
            'short_name' => 'Sofor',
            'description' => __('Split group trip costs fairly, settle up over bKash, and map everywhere you have been. Free for everyone.'),
            'id' => '/',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'en',
            // The app's paper colour, matching <meta name="theme-color">.
            'background_color' => '#fbf8f2',
            'theme_color' => '#fbf8f2',
            'categories' => ['travel', 'finance', 'productivity'],
            'icons' => $icons,
            'shortcuts' => [
                ['name' => 'Start a trip', 'url' => '/trips/create'],
                ['name' => 'My trips', 'url' => '/trips'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }
}
