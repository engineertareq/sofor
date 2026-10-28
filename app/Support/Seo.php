<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;

/**
 * One address per page per language, so search engines index both.
 *
 * The site's own language (Bangla) lives at the plain address; every other
 * language adds ?lang=xx (sofor.io/travel-map?lang=en). Each page names all
 * of its versions with hreflang links, and the sitemap lists them too.
 */
final class Seo
{
    public const PARAM = 'lang';

    /** The language at the plain address: what a visitor with no choice yet sees. */
    public static function defaultLocale(): string
    {
        return SetLocale::defaultFor(null);
    }

    /** This address in the given language. */
    public static function url(string $url, string $locale): string
    {
        $url = self::withoutLang($url);

        if ($locale === self::defaultLocale()) {
            return $url;
        }

        // The home page is "https://sofor.io": its English address is "https://sofor.io/?lang=en".
        if (! str_contains($url, '?') && in_array(parse_url($url, PHP_URL_PATH), [null, ''], true)) {
            $url .= '/';
        }

        return $url.(str_contains($url, '?') ? '&' : '?').self::PARAM.'='.$locale;
    }

    /** The address with any ?lang= taken out. */
    public static function withoutLang(string $url): string
    {
        $parts = explode('#', $url, 2);
        [$base, $query] = array_pad(explode('?', $parts[0], 2), 2, null);

        if ($query !== null) {
            parse_str($query, $params);
            unset($params[self::PARAM]);
            $base .= $params === [] ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $base.(isset($parts[1]) ? '#'.$parts[1] : '');
    }

    /**
     * Every language version of the address, for hreflang links:
     * ['bn' => ..., 'en' => ..., 'x-default' => ...].
     *
     * @return array<string, string>
     */
    public static function alternates(string $url): array
    {
        $links = [];

        foreach (array_keys(SetLocale::LOCALES) as $locale) {
            $links[$locale] = self::url($url, $locale);
        }

        $links['x-default'] = self::url($url, self::defaultLocale());

        return $links;
    }

    /** Text for a meta tag: in Bangla its digits are Bangla too, as on the page. */
    public static function text(string $value): string
    {
        return Bangla::localize($value);
    }
}
