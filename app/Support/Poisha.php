<?php

namespace App\Support;

/**
 * Money on the API is whole poisha (125050 is 1,250.50 taka), so a phone never
 * does arithmetic on fractions. The database keeps taka with two decimals.
 */
class Poisha
{
    public static function of(float|int|string|null $taka): int
    {
        return (int) round((float) $taka * 100);
    }

    /** Null stays null: "no budget" is not the same as a budget of zero. */
    public static function orNull(float|int|string|null $taka): ?int
    {
        return $taka === null ? null : self::of($taka);
    }

    /** What the app sent, as taka for the rules and services the website uses. Not a number: left for validation to refuse. */
    public static function toTaka(mixed $poisha): mixed
    {
        if ($poisha === null || $poisha === '' || ! is_numeric($poisha)) {
            return $poisha;
        }

        return round(((float) $poisha) / 100, 2);
    }
}
