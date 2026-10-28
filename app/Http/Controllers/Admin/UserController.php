<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $role = $request->string('role')->toString() ?: 'all';
        $status = $request->string('status')->toString() ?: 'all';

        $users = User::query()
            ->search($search)
            ->when($role !== 'all', fn ($query) => $query->where('role', $role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'paused', fn ($query) => $query->where('is_active', false))
            ->withCount(['ownedTrips', 'memberships'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => ['q' => $search, 'role' => $role, 'status' => $status],
            'counts' => [
                'all' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'paused' => User::where('is_active', false)->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('members')
            ->withSum('expenses', 'amount')
            ->latest()
            ->take(15)
            ->get();

        $memberships = TripMember::query()
            ->where('user_id', $user->id)
            ->with('trip')
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'trips' => $trips,
            'memberships' => $memberships,
            'stats' => [
                'trips_led' => $user->ownedTrips()->count(),
                'trips_joined' => $memberships->where('trip.user_id', '!=', $user->id)->count(),
                'spend' => (float) TripMember::query()
                    ->where('user_id', $user->id)
                    ->join('expenses', 'expenses.trip_member_id', '=', 'trip_members.id')
                    ->sum('expenses.amount'),
                'notifications' => $user->notifications()->count(),
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:user,admin'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->with('error', 'You cannot remove your own admin access or pause your own account.');
        }

        $user->forceFill([
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ])->save();

        return back()->with('status', $user->name.' updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot pause your own account.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with('status', $user->is_active
            ? $user->name.' can sign in again.'
            : $user->name.' has been paused.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account from here.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', $name.' and all related data were deleted.');
    }
}
