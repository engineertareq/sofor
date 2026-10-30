<?php

namespace App\Enums;

/**
 * Whether a registration holds its seats. Whether it is paid is worked out
 * from its payments (TourRegistration::paid), never stored.
 */
enum TourBookingStatus: string
{
    // Holds its seats.
    case Booked = 'booked';
    // The tour was full: waits for a seat to free up.
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('Booked'),
            self::Waitlisted => __('Waitlist'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Booked => 'positive',
            self::Waitlisted => 'warning',
            self::Cancelled => 'muted',
        };
    }
}
