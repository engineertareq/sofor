<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\SplitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trip_id',
        'trip_member_id',
        'created_by',
        'title',
        'category',
        'amount',
        'spent_on',
        'split_type',
        'notes',
        'items',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'split_type' => SplitType::class,
            'amount' => 'decimal:2',
            'spent_on' => 'date',
            'items' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(TripMember::class, 'trip_member_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class);
    }

    public function hasReceipt(): bool
    {
        return $this->receipt_path !== null;
    }

    public function receiptUrl(): ?string
    {
        // The file name changes on every upload, so it doubles as a cache buster.
        return $this->receipt_path === null ? null : route('trips.expenses.receipt', [
            $this->trip_id,
            $this->id,
            'v' => substr(md5($this->receipt_path), 0, 8),
        ]);
    }

    public function isItemised(): bool
    {
        return $this->split_type === SplitType::Itemised && ! empty($this->items);
    }

    public function categoryEnum(): ExpenseCategory
    {
        return $this->category instanceof ExpenseCategory
            ? $this->category
            : ExpenseCategory::from((string) $this->category);
    }
}
