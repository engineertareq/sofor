<?php

namespace App\Support;

use Illuminate\Translation\Translator;

/**
 * Bangla sentences attach case endings to the placeholder before them:
 * ":place-এর এক্সপ্লোরার". With an English name the hyphen stays
 * ("Sajek-এর"), but a Bangla name takes the ending the way Bangla writes it:
 * "বাংলাদেশের", "ঢাকায়", "রাঙামাটিতে".
 */
class BanglaTranslator extends Translator
{
    protected function makeReplacements($line, array $replace)
    {
        if (is_string($line) && $replace !== [] && preg_match('/-(এর|এ|কে|তে)/u', $line)) {
            foreach ($replace as $key => $value) {
                if (! is_string($value) && ! $value instanceof \Stringable) {
                    continue;
                }

                $line = preg_replace_callback(
                    '/:'.preg_quote((string) $key, '/').'-(এর|এ|কে|তে)(?![\x{0980}-\x{09FF}])/u',
                    fn (array $m) => ($ending = Bangla::ending((string) $value, $m[1])) === null ? $m[0] : ':'.$key.$ending,
                    $line,
                );
            }
        }

        return parent::makeReplacements($line, $replace);
    }
}
