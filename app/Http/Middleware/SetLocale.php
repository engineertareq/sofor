<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sofor speaks Bangla and English. An address that names its language
 * (?lang=en, the English version search engines index - see App\Support\Seo)
 * wins and is remembered for the visit. Otherwise the traveller's saved
 * choice, then the choice made this visit, then the site's own language -
 * Bangla (config app.interface_locale). The browser's language is not used:
 * nearly every phone in Bangladesh is set to English.
 */
class SetLocale
{
    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];

    public function handle(Request $request, Closure $next): Response
    {
        $asked = $request->query(Seo::PARAM);

        if (is_string($asked) && array_key_exists($asked, self::LOCALES)) {
            $locale = $asked;
            $request->hasSession() && $request->session()->put('locale', $asked);
        } else {
            $locale = self::remembered($request);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    /**
     * The language this visit has settled on: the one a page without ?lang=
     * is shown in. The other open tabs follow it (resources/js/tab-sync.js).
     */
    public static function remembered(Request $request): string
    {
        $user = $request->user();
        $locale = $user?->locale ?? ($request->hasSession() ? $request->session()->get('locale') : null);

        return array_key_exists((string) $locale, self::LOCALES) ? $locale : self::defaultFor($user);
    }

    /** The language for someone who has not picked one: the console is English, everything else Bangla. */
    public static function defaultFor(?User $user): string
    {
        if ($user?->isAdmin()) {
            return 'en';
        }

        $locale = (string) config('app.interface_locale', 'bn');

        return array_key_exists($locale, self::LOCALES) ? $locale : 'bn';
    }
}
