<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\TripJoinController;
use App\Models\User;
use App\Services\TripInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared by the login, registration and verification controllers: when
 * somebody arrived from an invite link, finishing authentication joins that
 * trip immediately instead of dropping them on an empty dashboard.
 */
trait HandlesPendingInvites
{
    protected function redirectToPendingInvite(Request $request, TripInvitationService $invitations, User $user): ?RedirectResponse
    {
        // Not until the account is verified. The invite stays in the session,
        // so verifying carries straight on into the trip.
        if (! $user->hasVerifiedContact()) {
            return null;
        }

        $invite = $invitations->acceptInviteCode(
            $user,
            $request->session()->pull(TripJoinController::SESSION_KEY),
        );

        if ($invite === null) {
            return null;
        }

        $trip = $invite['trip'];

        if ($invite['ended']) {
            // The trip is closed, so send them to the read only preview instead
            // of a trip dashboard they have no access to.
            return redirect()
                ->route('trips.join.show', $trip->invite_code)
                ->with('error', __('That invite link belongs to :trip, which has already ended.', ['trip' => $trip->title]));
        }

        return redirect()->route('trips.show', $trip)->with('status', $invite['joined'] ? __('You joined :trip. Your share of every cost is now visible.', ['trip' => $trip->title]) : __('You are already travelling on :trip. Your share is up to date.', ['trip' => $trip->title]));
    }

    /**
     * Straight after an email or phone is verified: pick up the trip invites
     * waiting for it, carry on into an invite link, or go where they meant to.
     */
    protected function finishVerification(Request $request, TripInvitationService $invitations, User $user, string $message): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard')->with('status', $message);
        }

        $claimed = $invitations->claimPendingFor($user);

        if (($invited = $this->redirectToPendingInvite($request, $invitations, $user)) !== null) {
            return $invited;
        }

        if ($claimed->isNotEmpty()) {
            $message .= ' '.($claimed->count() === 1
                ? __('We also added you to 1 pending trip invite.')
                : __('We also added you to :count pending trip invites.', ['count' => $claimed->count()]));
        }

        return $this->intendedFor($request, $user)->with('status', $message);
    }

    /**
     * Where they meant to go before signing in, when it is a page for this kind
     * of account; their home page otherwise. The browser's session remembers the
     * address, so it can belong to whoever used it last: an admin page reloaded
     * after an admin signed out must not send the next traveller to /admin.
     */
    protected function intendedFor(Request $request, User $user): RedirectResponse
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $console = $path === '/admin' || str_starts_with($path, '/admin/');
        $ours = str_starts_with($intended, url('/').'/') || $intended === url('/');

        return redirect()->to($ours && $console === $user->isAdmin() ? $intended : $user->homeUrl());
    }
}
