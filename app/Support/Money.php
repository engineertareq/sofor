<?php

namespace App\Support;

/**
 * How Sofor writes money. In Bangla, amounts use Bangla digits and the
 * lakh grouping Bangladesh reads naturally: ৳১,২৩,৪৫৬.
 */
class Money
{
    public const SYMBOLS = [
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'AED' => 'AED ',
        'MYR' => 'RM ',
        'SGD' => 'S$',
        'THB' => '฿',
    ];

    /** Currencies written with lakh grouping in Bangla. */
    private const SOUTH_ASIAN = ['BDT', 'INR'];

    public static function symbol(string $currency = 'BDT'): string
    {
        return self::SYMBOLS[$currency] ?? $currency.' ';
    }

    public static function format(float|int|string|null $amount, string $currency = 'BDT', bool $symbol = true): string
    {
        $value = (float) ($amount ?? 0);
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

        return ($symbol ? self::symbol($currency) : '').self::number($value, $decimals, $currency);
    }

    /** ৳1.2k style short formatting for stat tiles (৳১.২ হা. in Bangla). */
    public static function compact(float|int|string|null $amount, string $currency = 'BDT'): string
    {
        $value = (float) ($amount ?? 0);
        $symbol = self::symbol($currency);
        $bangla = Bangla::active();
        $short = fn (float $n, int $decimals) => Bangla::localize(rtrim(rtrim(number_format($n, $decimals), '0'), '.'));

        return match (true) {
            abs($value) >= 10000000 => $symbol.' '.$short($value / 10000000, 2).($bangla ? ' কোটি' : 'Cr'),
            abs($value) >= 100000 => $symbol.' '.$short($value / 100000, 2).($bangla ? ' লাখ' : 'L'),
            abs($value) >= 1000 => $symbol.' '.$short($value / 1000, 1).($bangla ? ' হাজার' : 'k'),
            default => $symbol.' '.self::number($value, 0, $currency),
        };
    }

    public static function percent(float $value): string
    {
        return Bangla::localize(rtrim(rtrim(number_format($value, 1), '0'), '.')).'%';
    }

    /** A plain number the way the current language writes it. */
    public static function number(float|int $value, int $decimals = 0, string $currency = 'BDT'): string
    {
        if (! Bangla::active()) {
            return number_format($value, $decimals);
        }

        $grouped = in_array($currency, self::SOUTH_ASIAN, true)
            ? Bangla::group((float) $value, $decimals)
            : number_format($value, $decimals);

        return Bangla::digits($grouped);
    }
}
