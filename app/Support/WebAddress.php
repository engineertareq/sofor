<?php

namespace App\Support;

/** Website fields: people type "example.com", the url rule wants a scheme. */
final class WebAddress
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $value) === 1 ? $value : 'https://'.ltrim($value, '/');
    }
}
