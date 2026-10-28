<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case Upay = 'upay';
    case Cash = 'cash';
    case Bank = 'bank';
    case Other = 'other';
    // Written by "settle up across trips": balanced out against another trip, no money moved.
    case Netted = 'netted';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => __('bKash'),
            self::Nagad => __('Nagad'),
            self::Rocket => __('Rocket'),
            self::Upay => __('Upay'),
            self::Cash => __('Cash'),
            self::Bank => __('Bank transfer'),
            self::Other => __('Other'),
            self::Netted => __('Netted across trips'),
        };
    }

    /** The ways a person can say they paid. */
    public static function payable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case !== self::Netted));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::payable() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
