<?php

namespace App\Models;

use App\Support\Phrase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'trip_id',
        'user_id',
        'trip_member_id',
        'type',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }

    /** What happened, in the reader's language. */
    public function text(): string
    {
        $phrase = $this->meta['phrase'] ?? null;

        return is_array($phrase) ? Phrase::fromArray($phrase)->render() : (string) $this->description;
    }
}
