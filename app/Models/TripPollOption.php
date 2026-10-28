<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripPollOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'position'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(TripPoll::class, 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(TripPollVote::class, 'option_id');
    }
}
