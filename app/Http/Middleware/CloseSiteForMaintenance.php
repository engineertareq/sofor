<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin has Sofor in maintenance mode, every web request from anybody
 * who is not an administrator gets the maintenance page (errors/503).
 *
 * Runs right after the session starts (see bootstrap/app.php), so it knows who
 * is signed in, and before route model binding, so a closed site does not
 * reveal which trips exist.
 */
class CloseSiteForMaintenance
{
    /**
     * Open to everyone even while closed: the sign in screen, so an admin on a
     * new device can still get in, the branding the maintenance page shows,
     * and robots.txt and the sitemap - a search engine that cannot read them
     * for long enough stops crawling the site at all.
     */
    private const ALWAYS_OPEN = ['login', 'login.store', 'logout', 'branding.asset', 'manifest', 'robots', 'sitemap'];

    public function __construct(private readonly MaintenanceMode $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->active()
            || $request->user()?->isAdmin()
            || $request->routeIs(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        abort(503, $this->maintenance->message() ?? __('Sofor is down for maintenance. Please try again soon.'), [
            'Retry-After' => '300',
        ]);
    }
}
