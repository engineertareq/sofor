<?php

use App\Http\Middleware\Api\CheckAppVersion;
use App\Http\Middleware\Api\EnsureVerified;
use App\Http\Middleware\Api\ForceJson;
use App\Http\Middleware\Api\TravellersOnly;
use App\Http\Middleware\Api\UseRequestLanguage;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\CloseSiteForMaintenance;
use App\Http\Middleware\EnsureContactIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsTraveller;
use App\Http\Middleware\LocalizeDigits;
use App\Http\Middleware\NormalizeBanglaDigits;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SignOutPausedAccounts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The Android app. A change that would break an installed app goes to /api/v2.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'traveller' => EnsureUserIsTraveller::class,
            'verified.contact' => EnsureContactIsVerified::class,
            'api.traveller' => TravellersOnly::class,
            'api.verified' => EnsureVerified::class,
        ]);

        // Maintenance mode needs the session (to let admins through) but must run
        // before route model binding, so it sits right after the session starts.
        $middleware->web(append: [SignOutPausedAccounts::class, CloseSiteForMaintenance::class, SetLocale::class, LocalizeDigits::class, NormalizeBanglaDigits::class]);

        // The app's API: always JSON, in the language the app asks for, closed
        // with the site during maintenance. No session, no Bangla digits in JSON.
        $middleware->api(
            prepend: [ForceJson::class],
            append: [CloseSiteForMaintenance::class, UseRequestLanguage::class, CheckAppVersion::class, NormalizeBanglaDigits::class],
        );

        // Global, so error pages and the maintenance page carry the headers too.
        $middleware->append(SecurityHeaders::class);

        // First of all: www., /public/ and trailing-slash addresses go to the one real address.
        $middleware->prepend(CanonicalHost::class);
        $middleware->appendToPriorityList(ShareErrorsFromSession::class, CloseSiteForMaintenance::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->expectsJson() || $request->is('api/*'));
    })->create();
