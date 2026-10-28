<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A "you still owe" nudge that went out, by hand or automatically. */
class PaymentReminder extends Model
{
    protected $fillable = ['trip_id', 'trip_member_id', 'sent_by', 'amount', 'channels', 'automatic'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'automatic' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }
}
