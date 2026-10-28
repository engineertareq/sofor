<?php

namespace App\Support;

/**
 * Normalises Bangladeshi phone numbers so that invites and logins can be
 * matched no matter how the number was typed (01XXXXXXXXX, +8801XXXXXXXXX, ...).
 */
class Phone
{
    public const DEFAULT_COUNTRY_CODE = '880';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // 8801XXXXXXXXX -> 01XXXXXXXXX
        if (str_starts_with($digits, self::DEFAULT_COUNTRY_CODE) && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 3);
        }

        // 8801XXXXXXXXX typed with a leading zero stripped, e.g. 1XXXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        return '+'.self::DEFAULT_COUNTRY_CODE.substr($digits, 1);
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== null && preg_match('/^\+8801[3-9]\d{8}$/', $normalized) === 1;
    }

    public static function pretty(?string $value): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        // +8801922992279 -> +880 1922-992279
        return substr($normalized, 0, 4).' '.substr($normalized, 4, 4).'-'.substr($normalized, 8);
    }

    public static function isEmail(?string $value): bool
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** True when the identifier looks like a phone number rather than an email. */
    public static function looksLikePhone(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return ! self::isEmail($value) && preg_match('/^[+\d][\d\s\-()]{5,}$/', trim($value)) === 1;
    }
}
