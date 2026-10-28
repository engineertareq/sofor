<?php

namespace App\Enums;

enum MemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Trip leader'),
            self::Member => __('Traveller'),
        };
    }
}
