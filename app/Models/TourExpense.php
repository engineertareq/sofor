<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a tour cost its organiser: the bus, the hotel, the guide. */
class TourExpense extends Model
{
    protected $fillable = [
        'title',
        'amount',
        'category',
        'spent_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'spent_on' => 'date',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
