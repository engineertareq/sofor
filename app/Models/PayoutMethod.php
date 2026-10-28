<?php

namespace App\Models;

use App\Enums\PayoutProvider;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a traveller wants to be paid. Trip dues are paid person to person,
 * straight into this account - Sofor never holds the money.
 */
class PayoutMethod extends Model
{
    public const MAX_PER_USER = 6;

    protected $fillable = [
        'provider',
        'account_number',
        'account_name',
        'bank_name',
        'branch_name',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PayoutProvider::class,
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The number as people type it: 01712-345678 for wallets, as entered for banks. */
    public function displayNumber(): string
    {
        if ($this->provider === PayoutProvider::Rocket) {
            // Rocket accounts are the mobile number plus a check digit.
            return substr($this->account_number, 0, 5).'-'.substr($this->account_number, 5, 6).'-'.substr($this->account_number, 11);
        }

        if ($this->provider->isMobileWallet()) {
            $local = '0'.substr((string) Phone::normalize($this->account_number), 4);

            return substr($local, 0, 5).'-'.substr($local, 5);
        }

        return $this->account_number;
    }

    /** What the copy button puts on the clipboard: digits only, ready to paste into the app. */
    public function copyValue(): string
    {
        if ($this->provider->isMobileWallet() && $this->provider !== PayoutProvider::Rocket) {
            return '0'.substr((string) Phone::normalize($this->account_number), 4);
        }

        return $this->account_number;
    }

    /** "Dutch-Bangla Bank, Gulshan branch" for banks, "Personal" for wallets. */
    public function detail(): string
    {
        if ($this->provider === PayoutProvider::Bank) {
            return collect([$this->bank_name, $this->branch_name ? __(':branch branch', ['branch' => $this->branch_name]) : null])->filter()->implode(', ');
        }

        return __('Personal');
    }
}
