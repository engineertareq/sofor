<?php

namespace App\Support;

/**
 * Translation for the shared components. Labels arrive as plain strings, as
 * HtmlString objects or as null; only plain strings are looked up in
 * lang/bn.json, and anything without a translation is shown as written.
 */
final class Lang
{
    public static function t(mixed $value): mixed
    {
        return is_string($value) && $value !== '' ? __($value) : $value;
    }
}
