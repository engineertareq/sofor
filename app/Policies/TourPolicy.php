<?php

namespace App\Policies;

use App\Models\Tour;
use App\Models\User;

class TourPolicy
{
    /** Only its organiser runs a tour: the bookings, the money and the page. */
    public function manage(User $user, Tour $tour): bool
    {
        return $tour->user_id === $user->id;
    }
}
