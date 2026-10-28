<?php

namespace App\Enums;

/** Where a traveller can be paid: the mobile wallets Bangladesh uses, or a bank. */
enum PayoutProvider: string
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case Upay = 'upay';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => __('bKash'),
            self::Nagad => __('Nagad'),
            self::Rocket => __('Rocket'),
            self::Upay => __('Upay'),
            self::Bank => __('Bank account'),
        };
    }

    /** Mobile financial services are a phone number; a bank is not. */
    public function isMobileWallet(): bool
    {
        return $this !== self::Bank;
    }

    /** The brand colour, for the small badge next to a number. */
    public function color(): string
    {
        return match ($this) {
            self::Bkash => '#e2136e',
            self::Nagad => '#ec1c24',
            self::Rocket => '#8c3494',
            self::Upay => '#0057a8',
            self::Bank => '#0f6f6a',
        };
    }

    /** The matching way a payment is recorded in the ledger. */
    public function paymentMethod(): PaymentMethod
    {
        return match ($this) {
            self::Bkash => PaymentMethod::Bkash,
            self::Nagad => PaymentMethod::Nagad,
            self::Rocket => PaymentMethod::Rocket,
            self::Upay => PaymentMethod::Upay,
            self::Bank => PaymentMethod::Bank,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
