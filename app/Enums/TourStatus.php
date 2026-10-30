<?php

namespace App\Enums;

/**
 * Where an organised tour stands. Only an open tour takes registrations;
 * a closed one keeps its bookings and payments going.
 */
enum TourStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Open => __('Registration open'),
            self::Closed => __('Registration closed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** The badge tone (components/badge). */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'positive',
            self::Closed => 'warning',
            self::Cancelled => 'negative',
            default => 'muted',
        };
    }

    /** Payments and costs can still be recorded: not once the tour is called off. */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }
}
