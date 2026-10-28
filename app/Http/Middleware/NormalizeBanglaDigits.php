<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * A Bangla keyboard types ০-৯. Amounts, phone numbers, codes and dates are
 * read as 0-9, so "৫০" tops up ৳50 and "০১৭১২৩৪৫৬৭৮" is a valid number.
 * Only values with no letters at all are touched: a trip called
 * "সাজেক ২০২৬" or a note keeps its Bangla digits.
 */
class NormalizeBanglaDigits extends TransformsRequest
{
    private const DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /** Passwords are compared as typed. */
    private const EXCEPT = ['password', 'password_confirmation', 'current_password'];

    protected function transform($key, $value)
    {
        if (! is_string($value) || in_array($key, self::EXCEPT, true) || ! preg_match('/[০-৯]/u', $value) || preg_match('/\p{L}/u', $value)) {
            return $value;
        }

        return strtr($value, self::DIGITS);
    }
}
