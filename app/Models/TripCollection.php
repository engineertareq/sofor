<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An advance a trip collects: so much per traveller, paid to one collector.
 * In the ledger it is plain arithmetic - each advance is a payment to the
 * collector, and costs paid "from the pot" are costs the collector paid - so
 * whatever is left over at the end shows up as the collector owing it back.
 */
class TripCollection extends Model
{
    protected $fillable = [
        'collector_member_id',
        'created_by',
        'title',
        'amount_per_person',
        'due_on',
        'note',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_per_person' => 'decimal:2',
            'due_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'collector_member_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Settlement::class, 'collection_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
