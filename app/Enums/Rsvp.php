<?php

namespace App\Enums;

/** A traveller's answer to "are you coming?". */
enum Rsvp: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case NotGoing = 'not_going';

    public function label(): string
    {
        return match ($this) {
            self::Going => __('Going'),
            self::Maybe => __('Maybe'),
            self::NotGoing => __("Can't make it"),
        };
    }

    /** The untranslated label, for sentences translated later (Phrase). */
    public function key(): string
    {
        return match ($this) {
            self::Going => 'Going',
            self::Maybe => 'Maybe',
            self::NotGoing => "Can't make it",
        };
    }

    /** The answer's picture (App\Support\Glyphs). */
    public function icon(): string
    {
        return match ($this) {
            self::Going => 'going',
            self::Maybe => 'maybe',
            self::NotGoing => 'not-going',
        };
    }
}
