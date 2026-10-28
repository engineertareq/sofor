<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseShare extends Model
{
    protected $fillable = [
        'expense_id',
        'trip_member_id',
        'amount',
        'units',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'units' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }
}
