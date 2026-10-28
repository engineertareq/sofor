<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A quick question the group votes on inside a trip. */
class TripPoll extends Model
{
    public const MAX_OPTIONS = 6;

    public const MAX_OPEN = 8;

    protected $fillable = [
        'created_by',
        'question',
        'multiple',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TripPollOption::class, 'poll_id')->orderBy('position')->orderBy('id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(TripPollVote::class, 'poll_id');
    }

    /** Open until somebody closes it or its closing time passes. */
    public function isOpen(): bool
    {
        return $this->closed_at === null && ($this->closes_at === null || $this->closes_at->isFuture());
    }
}
