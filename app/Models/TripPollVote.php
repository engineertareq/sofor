<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPollVote extends Model
{
    protected $fillable = ['poll_id', 'option_id', 'trip_member_id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }
}
