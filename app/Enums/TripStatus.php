<?php

namespace App\Enums;

enum TripStatus: string
{
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Live'),
            self::Ended => __('Ended'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'lime',
            self::Ended => 'slate',
        };
    }
}
