<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $fillable = [
        'trip_id',
        'from_member_id',
        'to_member_id',
        'amount',
        'charge',
        'method',
        'gateway',
        'gateway_trx_id',
        'reference',
        'note',
        'collection_id',
        'batch',
        'recorded_by',
        'settled_on',
        'confirmed_at',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'charge' => 'decimal:2',
            'settled_on' => 'date',
            'confirmed_at' => 'datetime',
            'method' => PaymentMethod::class,
        ];
    }

    public function paidThroughGateway(): bool
    {
        return $this->gateway !== null;
    }

    /** Reported by the payer and not yet confirmed by the receiver. */
    public function isPending(): bool
    {
        return $this->confirmed_at === null;
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(TripCollection::class, 'collection_id');
    }

    /** What the traveller actually handed over, including the MFS charge. */
    public function totalPaid(): float
    {
        return round((float) $this->amount + (float) $this->charge, 2);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'from_member_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'to_member_id');
    }

    public function methodEnum(): PaymentMethod
    {
        return $this->method instanceof PaymentMethod
            ? $this->method
            : PaymentMethod::from((string) $this->method);
    }
}
