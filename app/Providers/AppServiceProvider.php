<?php

namespace App\Providers;

use App\Http\Controllers\TripJoinController;
use App\Models\Trip;
use App\Services\BkashService;
use App\Services\BrandingService;
use App\Services\MaintenanceMode;
use App\Services\SiteSettings;
use App\Services\TravelMaps;
use App\Support\BanglaTranslator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The gateway talks to the outside world, so it is always built from the
        // current configuration instead of being auto wired with null values.
        $this->app->bind(BkashService::class, fn () => BkashService::fromConfig());

        // Read on every request (maintenance, logo, favicon), so the settings
        // table is queried once per request.
        $this->app->singleton(SiteSettings::class);

        // Bangla case endings join Bangla names ("বাংলাদেশের", not "বাংলাদেশ-এর").
        $this->app->extend('translator', function (Translator $translator) {
            $bangla = new BanglaTranslator($translator->getLoader(), $translator->getLocale());
            $bangla->setFallback($translator->getFallback());

            return $bangla;
        });
        $this->app->singleton(BrandingService::class);
        $this->app->singleton(MaintenanceMode::class);

        // Map geometry is read from disk once per request at most.
        $this->app->singleton(TravelMaps::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.sofor');
        Paginator::defaultSimpleView('vendor.pagination.sofor');

        // Somebody arriving from an invite link should see which trip they are
        // about to join while they sign in or register.
        View::composer(['auth.login', 'auth.register'], function ($view) {
            $code = session(TripJoinController::SESSION_KEY);

            $view->with('pendingInvite', $code !== null
                ? Trip::where('invite_code', $code)->first()
                : null);
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
