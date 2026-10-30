<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One way to join a tour and its price: "Regular", "Couple (2 seats)", "Student". */
class TourPackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'seats',
        'advance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'advance' => 'decimal:2',
            'seats' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TourRegistration::class);
    }

    /** What holds a booking: the advance, or the full price when there is none. */
    public function advanceAmount(): float
    {
        return (float) ($this->advance ?? $this->price);
    }
}
