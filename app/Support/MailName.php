<?php

namespace App\Support;

/**
 * A person's first name, safe to put in a Markdown email. Names are typed by
 * whoever signs up - possibly with somebody else's address - so link and
 * formatting syntax is stripped: "[Claim prize](https://...)" must never
 * reach an inbox as a link sent from Sofor.
 */
final class MailName
{
    public static function first(?string $name): string
    {
        $first = (string) strtok(trim((string) $name), " \t\n");
        // Letters with their marks (Bangla vowel signs are marks), digits, ' and -.
        // No dots, so nothing left over can look like a web address.
        $first = preg_replace("/[^\\pL\\pM\\pN'-]/u", '', $first) ?? '';

        return mb_substr($first, 0, 40) !== '' ? mb_substr($first, 0, 40) : 'there';
    }

    /**
     * Free text from a traveller (a trip title, a name) made safe for a
     * Markdown email: no link, emphasis or HTML syntax survives, and dots
     * between letters are broken so nothing reads as a web address.
     */
    public static function text(?string $value, int $limit = 80): string
    {
        $clean = preg_replace('/[\[\]()<>*_`#|\\\\]/u', '', trim((string) $value)) ?? '';
        $clean = preg_replace('/(\w)\.(\w)/u', '$1. $2', $clean) ?? $clean;

        return mb_strimwidth($clean !== '' ? $clean : 'your trip', 0, $limit, '...');
    }
}
