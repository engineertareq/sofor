<?php

namespace App\Support;

/**
 * Native Bangla presentation: Bangla digits (০-৯) and lakh grouping
 * (১,২৩,৪৫৬), used whenever the interface is in Bangla.
 *
 * Phone numbers, transaction ids, codes and links keep Latin digits - they
 * are typed and copied into bKash and other apps - so the HTML converter
 * leaves alone anything inside <code>, <pre>, <script>, <style>,
 * <textarea>, a `font-mono` element, or an element marked translate="no".
 */
final class Bangla
{
    private const DIGITS = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    /** Elements whose text is never converted. */
    private const KEEP_LATIN = ['script', 'style', 'textarea', 'code', 'pre', 'kbd', 'samp'];

    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'stop', 'use'];

    public static function active(): bool
    {
        return app()->getLocale() === 'bn';
    }

    /** "Mon 19 Oct, 5:22 pm"; in Bangla the part of the day leads: "সোম ১৯ অক্টো, বিকাল ৫:২২". */
    public static function moment(\Carbon\CarbonInterface $at): string
    {
        return self::active()
            ? self::digits($at->copy()->locale('bn')->isoFormat('ddd D MMM, A h:mm'))
            : $at->format('D j M, g:i a');
    }

    /**
     * How a case ending joins a Bangla word: "বাংলাদেশ" + "এর" is "বাংলাদেশের",
     * "ঢাকা" + "এ" is "ঢাকায়". Null when the word does not end in a Bangla
     * letter (a Latin name, a figure), which keeps the hyphenated form.
     */
    public static function ending(string $word, string $suffix): ?string
    {
        $last = mb_substr(rtrim($word), -1);

        if ($last === '' || $last === 'ৎ' || ! preg_match('/[\x{0981}-\x{0983}\x{0985}-\x{09B9}\x{09BC}-\x{09D7}\x{09DC}-\x{09DF}]/u', $last)) {
            return null;
        }

        $vowel = preg_match('/[\x{0985}-\x{0994}\x{09BE}-\x{09CC}\x{09D7}]/u', $last) === 1;
        $nasal = preg_match('/[\x{0981}-\x{0983}]/u', $last) === 1;

        return match ($suffix) {
            'এর' => $vowel ? 'র' : ($nasal ? 'য়ের' : 'ের'),
            'এ' => $last === 'া' ? 'য়' : ($vowel ? 'তে' : ($nasal ? 'য়ে' : 'ে')),
            default => $suffix,
        };
    }

    public static function digits(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    /** Bangla digits when the interface is in Bangla, unchanged otherwise. */
    public static function localize(string|int|float $value): string
    {
        return self::active() ? self::digits((string) $value) : (string) $value;
    }

    /** 1234567.5 -> "12,34,567.50": the lakh and crore grouping used in Bangladesh. */
    public static function group(float $value, int $decimals = 0): string
    {
        $negative = $value < 0;
        [$whole, $fraction] = array_pad(explode('.', number_format(abs($value), $decimals, '.', '')), 2, null);

        if (strlen($whole) > 3) {
            $last = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = ltrim(preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest), ',').','.$last;
        }

        return ($negative ? '-' : '').$whole.($fraction !== null ? '.'.$fraction : '');
    }

    /**
     * Convert the digits in an HTML page's text to Bangla, leaving tags,
     * attributes, entities, scripts and Latin-only elements untouched.
     */
    public static function html(string $html): string
    {
        // Scripts, styles and textareas pass through whole. A tag runs to the
        // first ">" outside quotes: Alpine attributes hold code like
        // "m => m.name" or "count < 40" that must stay untouched.
        $attributes = '(?>[^>"\']+|"[^"]*"|\'[^\']*\')*';
        $raw = implode('|', array_map(fn (string $tag) => "<{$tag}\\b{$attributes}>.*?<\\/{$tag}\\s*>", ['script', 'style', 'textarea']));
        $parts = preg_split("/(<!--.*?-->|{$raw}|<[a-zA-Z\\/!]{$attributes}>)/is", $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        $skip = [];
        $out = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($part[0] === '<') {
                $out .= $part;

                if (str_starts_with($part, '<!') || preg_match('#^<(script|style|textarea)\b.*</\1\s*>$#is', $part)) {
                    continue;
                }

                if (preg_match('#^</\s*([a-zA-Z][\w:-]*)#', $part, $match)) {
                    $name = strtolower($match[1]);
                    if ($skip !== [] && end($skip)[0] === $name) {
                        $top = array_pop($skip);
                        if (--$top[1] > 0) {
                            $skip[] = $top;
                        }
                    }

                    continue;
                }

                if (preg_match('#^<\s*([a-zA-Z][\w:-]*)#', $part, $match)) {
                    $name = strtolower($match[1]);
                    $selfClosing = str_ends_with(rtrim($part, " \t\n\r>"), '/') || in_array($name, self::VOID, true);

                    if ($selfClosing) {
                        continue;
                    }

                    if ($skip !== [] && end($skip)[0] === $name) {
                        $top = array_pop($skip);
                        $top[1]++;
                        $skip[] = $top;

                        continue;
                    }

                    $latin = in_array($name, self::KEEP_LATIN, true)
                        || preg_match('/\btranslate\s*=\s*["\']no["\']/i', $part)
                        || preg_match('/\bclass\s*=\s*["\'][^"\']*\bfont-mono\b/i', $part);

                    if ($latin && $skip === []) {
                        $skip[] = [$name, 1];
                    }
                }

                continue;
            }

            // Text: convert digits, but not inside entities like &#039;, and not
            // where they touch Latin letters - a username (@tanvir1) or a code
            // (NG7731) is typed and copied exactly as it is.
            $out .= $skip === []
                ? preg_replace_callback('/(&#?\w+;)|(?<![A-Za-z_0-9])([0-9]+)(?![A-Za-z_0-9])/', fn ($m) => $m[1] !== '' ? $m[1] : self::digits($m[2]), $part)
                : $part;
        }

        return $out;
    }
}
