<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Accommodation = 'accommodation';
    case Transport = 'transport';
    case Food = 'food';
    case Activities = 'activities';
    case Tickets = 'tickets';
    case Shopping = 'shopping';
    case Guide = 'guide';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Accommodation => __('Stay'),
            self::Transport => __('Transport'),
            self::Food => __('Food'),
            self::Activities => __('Activities'),
            self::Tickets => __('Tickets'),
            self::Shopping => __('Shopping'),
            self::Guide => __('Guide'),
            self::Other => __('Other'),
        };
    }

    /** The category's picture (App\Support\Glyphs). */
    public function icon(): string
    {
        return match ($this) {
            self::Accommodation => 'stay',
            self::Transport => 'transport',
            self::Food => 'meal',
            self::Activities => 'activity',
            self::Tickets => 'ticket',
            self::Shopping => 'shopping',
            self::Guide => 'guide',
            self::Other => 'parcel',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
