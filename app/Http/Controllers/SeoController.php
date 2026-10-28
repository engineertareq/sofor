<?php

namespace App\Http\Controllers;

use App\Models\VisitedPlace;
use App\Services\TravelMaps;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * robots.txt and sitemap.xml, built here rather than kept as static files so
 * they always carry the real site address and match the routes that exist.
 */
class SeoController extends Controller
{
    /**
     * Paths that are private, per person, or carry codes and tokens. Crawlers
     * gain nothing there, and an invite preview shows a trip's money.
     */
    private const PRIVATE_PATHS = [
        '/dashboard', '/trips', '/map', '/notifications', '/profile', '/admin',
        '/verify', '/join/', '/p/', '/f/', '/wallet', '/forgot-password', '/reset-password', '/pass',
        '/username/', '/avatars/', '/support/callback', '/support/thanks', '/support/bkash/',
        '/sponsors/inquiries', '/timeline/',
    ];

    public function robots(): Response
    {
        // Only the live site is ever indexed: a staging or local copy must not
        // compete with it in search results.
        $lines = app()->environment('production')
            ? ['User-agent: *', ...array_map(fn (string $path) => 'Disallow: '.$path, self::PRIVATE_PATHS), 'Allow: /', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(TravelMaps $maps): Response
    {
        $view = fn (string $name) => Carbon::createFromTimestamp((int) @filemtime(resource_path('views/'.str_replace('.', '/', $name).'.blade.php')));

        $pages = [
            [route('home'), $view('home'), 'weekly', '1.0'],
            [route('map-maker'), $view('travel.maker'), 'weekly', '0.9'],
            [route('split-calculator'), $view('tools.split-calculator'), 'monthly', '0.8'],
            [route('explorers'), $this->latestVisit() ?? $view('explorers.index'), 'daily', '0.8'],
        ];

        // Every map after the first (Worldwide, and any country added later) has its own map maker and board.
        foreach (array_slice($maps->keys(), 1) as $key) {
            $pages[] = [route('map-maker', $key), $view('travel.maker'), 'weekly', '0.8'];
            $pages[] = [route('explorers', ['map' => $key]), $this->latestVisit() ?? $view('explorers.index'), 'daily', '0.7'];
        }

        array_push($pages,
            [route('sponsors'), $view('sponsors.index'), 'weekly', '0.6'],
            [route('support.index'), $view('support.index'), 'monthly', '0.5'],
            [route('about'), $view('pages.about'), 'monthly', '0.5'],
            [route('register'), $view('auth.register'), 'yearly', '0.4'],
            [route('login'), $view('auth.login'), 'yearly', '0.3'],
            [route('privacy'), $view('pages.privacy'), 'yearly', '0.2'],
            [route('terms'), $view('pages.terms'), 'yearly', '0.2'],
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        // Each language version is its own entry, and every entry names all of them (hreflang).
        foreach ($pages as [$url, $modified, $frequency, $priority]) {
            $alternates = Seo::alternates($url);
            $links = '';

            foreach ($alternates as $language => $href) {
                $links .= '<xhtml:link rel="alternate" hreflang="'.$language.'" href="'.htmlspecialchars($href, ENT_XML1).'"/>';
            }

            foreach (array_unique(array_diff_key($alternates, ['x-default' => true])) as $href) {
                $xml .= '  <url>'
                    .'<loc>'.htmlspecialchars($href, ENT_XML1).'</loc>'
                    .$links
                    .'<lastmod>'.$modified->toAtomString().'</lastmod>'
                    .'<changefreq>'.$frequency.'</changefreq>'
                    .'<priority>'.$priority.'</priority>'
                    .'</url>'."\n";
            }
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** When somebody last marked a place, which is when the Explorers boards last changed. */
    private function latestVisit(): ?Carbon
    {
        try {
            $latest = VisitedPlace::query()->max('created_at');
        } catch (Throwable) {
            return null;
        }

        return $latest !== null ? Carbon::parse($latest) : null;
    }
}
