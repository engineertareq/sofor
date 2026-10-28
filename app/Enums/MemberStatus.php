<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Declined = 'declined';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('In the trip'),
            self::Invited => __('Invite pending'),
            self::Declined => __('Declined'),
            self::Removed => __('Removed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'lime',
            self::Invited => 'amber',
            self::Declined => 'rose',
            self::Removed => 'slate',
        };
    }
}
