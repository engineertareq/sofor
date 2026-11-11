<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\HandlesPendingInvites;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TripJoinController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SignIn;
use App\Services\TripInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use HandlesPendingInvites;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, TripInvitationService $invitations, SignIn $signIn): RedirectResponse
    {
        $user = $signIn->attempt($request);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        // Admin accounts work from the console only, so trip invites are left
        // waiting rather than joined to an account that cannot open trips.
        if ($user->isAdmin()) {
            $request->session()->forget(TripJoinController::SESSION_KEY);

            return $this->intendedFor($request, $user)->with('status', __('Welcome back, :user!', ['user' => $user->name]));
        }

        $claimed = $invitations->claimPendingFor($user);

        // Arrived through an invite link? Join that trip straight away.
        $invited = $this->redirectToPendingInvite($request, $invitations, $user);

        if ($invited !== null) {
            return $invited;
        }

        $message = __('Welcome back, :user!', ['user' => $user->name]);

        if ($claimed->isNotEmpty()) {
            $message .= ' '.($claimed->count() === 1
                ? __('1 pending trip invite has been added to your trips.')
                : __(':count pending trip invites have been added to your trips.', ['count' => $claimed->count()]));
        }

        return $this->intendedFor($request, $user)->with('status', $message);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('You have been signed out.'));
    }
}
