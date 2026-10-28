<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An area (district, country...) a traveller has been to, on one travel map. */
class VisitedPlace extends Model
{
    protected $fillable = ['user_id', 'map', 'area', 'trip_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
