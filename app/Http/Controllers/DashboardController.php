<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripActivity;
use App\Models\TripMember;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortfolioService $portfolio): View
    {
        $user = $request->user();

        $trips = Trip::query()
            ->visibleTo($user)
            ->with(['owner', 'members'])
            ->withCount('expenses')
            ->withSum('expenses', 'amount')
            ->latest('last_activity_at')
            ->latest('id')
            ->get();

        $portfolio = $portfolio->forUser($user);

        $tripIds = $trips->pluck('id')->all();

        $activity = TripActivity::query()
            ->whereIn('trip_id', $tripIds)
            ->with(['trip', 'user'])
            ->latest('id')
            ->take(12)
            ->get();

        $pendingInvites = TripMember::query()
            ->where('user_id', $user->id)
            ->where('status', MemberStatus::Invited->value)
            ->count();

        $activeTrips = $trips->filter(fn (Trip $trip) => $trip->isActive());
        $upcoming = $trips
            ->filter(fn (Trip $trip) => $trip->isActive() && $trip->starts_on !== null && $trip->starts_on->isFuture())
            ->sortBy(fn (Trip $trip) => $trip->starts_on)
            ->take(4);

        return view('dashboard', [
            'user' => $user,
            'trips' => $trips,
            'activeTrips' => $activeTrips,
            'upcoming' => $upcoming,
            'activity' => $activity,
            'portfolio' => $portfolio,
            'pendingInvites' => $pendingInvites,
            'ledTrips' => $trips->where('user_id', $user->id)->count(),
            'joinedTrips' => $trips->where('user_id', '!=', $user->id)->count(),
        ]);
    }
}
