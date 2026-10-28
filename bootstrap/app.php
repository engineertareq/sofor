<?php

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
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'traveller' => EnsureUserIsTraveller::class,
            'verified.contact' => EnsureContactIsVerified::class,
        ]);

        // Maintenance mode needs the session (to let admins through) but must run
        // before route model binding, so it sits right after the session starts.
        $middleware->web(append: [SignOutPausedAccounts::class, CloseSiteForMaintenance::class, SetLocale::class, LocalizeDigits::class, NormalizeBanglaDigits::class]);

        // Global, so error pages and the maintenance page carry the headers too.
        $middleware->append(SecurityHeaders::class);

        // First of all: www., /public/ and trailing-slash addresses go to the one real address.
        $middleware->prepend(CanonicalHost::class);
        $middleware->appendToPriorityList(ShareErrorsFromSession::class, CloseSiteForMaintenance::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->expectsJson());
    })->create();
