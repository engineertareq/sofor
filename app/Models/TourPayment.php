<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money between a participant and the organiser: a payment in, or a refund
 * back out. One a participant reports waits (pending) until the organiser
 * finds it in their own bKash, Nagad or bank and confirms it.
 */
class TourPayment extends Model
{
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'amount',
        'method',
        'reference',
        'sender',
        'paid_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TourRegistration::class, 'tour_registration_id');
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** In for a payment, out (negative) for a refund. */
    public function signedAmount(): float
    {
        return $this->isRefund() ? -(float) $this->amount : (float) $this->amount;
    }
}
