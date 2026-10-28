<?php

namespace App\Enums;

enum SplitType: string
{
    case Equal = 'equal';
    case Custom = 'custom';
    case Itemised = 'itemised';

    public function label(): string
    {
        return match ($this) {
            self::Equal => __('Split equally'),
            self::Custom => __('Custom amounts'),
            self::Itemised => __('Itemised bill'),
        };
    }
}
