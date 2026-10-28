<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Reached Sajek", "Safe at the cottage": where the group is, for the people at home. */
class TripCheckin extends Model
{
    /** Quick picks on the check-in sheet. */
    public const PRESETS = [
        'Started the journey',
        'Reached',
        'Safe at the hotel',
        'Out exploring',
        'Heading back',
        'Home safe',
    ];

    protected $fillable = ['trip_member_id', 'place', 'note', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
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

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        return $this->hasLocation()
            ? 'https://www.google.com/maps/search/?api=1&query='.round($this->latitude, 5).','.round($this->longitude, 5)
            : null;
    }
}
