<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A voluntary contribution that keeps Sofor running. Donations are collected
 * through bKash Checkout, exactly like a traveller clearing their share, and are
 * never mixed into a trip ledger.
 *
 * The donor is charged the amount they chose, nothing on top. bKash still takes
 * its MFS fee, but Sofor absorbs that, so `amount` is the gift, `charge` is the
 * fee Sofor paid to receive it, and `total` is what the donor's bKash was
 * actually billed (which equals `amount`).
 */
class Donation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    /** Suggested one time gifts, in whole taka. */
    public const SUGGESTED = [100, 250, 500, 1000];

    protected $fillable = [
        'name',
        'contact',
        'message',
        'amount',
        'charge',
        'total',
        'percent',
        'invoice',
        'gateway',
        'payment_id',
        'trx_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'charge' => 'decimal:2',
            'total' => 'decimal:2',
            'percent' => 'decimal:2',
        ];
    }

    /** @param  Builder<Donation>  $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function donorLabel(): string
    {
        return $this->name ?: __('Kind supporter');
    }

    /** The gift, formatted the way the rest of the app formats money. */
    public function amountLabel(): string
    {
        return \App\Support\Money::format($this->amount);
    }

    public function totalLabel(): string
    {
        return \App\Support\Money::format($this->total);
    }
}
