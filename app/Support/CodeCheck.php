<?php

namespace App\Support;

/** The outcome of checking a texted or emailed code. */
enum CodeCheck
{
    case Valid;
    case Wrong;
    case Expired;
    case TooManyAttempts;

    /** What to tell the person, for every outcome but Valid. */
    public function message(bool $emailed = false): string
    {
        return match ($this) {
            self::Valid => '',
            self::Wrong => $emailed
                ? __('That code is not right. Check the email and try again.')
                : __('That code is not right. Check the text message and try again.'),
            self::Expired => __('That code has expired. Ask for a new one below.'),
            self::TooManyAttempts => __('Too many wrong tries for that code. Ask for a new one below.'),
        };
    }
}
