<?php

namespace App\Http\Controllers;

use App\Models\TripMember;
use Illuminate\Http\Response;

/**
 * /f/{token}: the page a traveller shares with their family. No account
 * needed. It shows the trip and its check-ins while the trip is on, and for
 * a few days after; then only that the trip is over.
 */
class FamilyLinkController extends Controller
{
    public const SHOW_DAYS_AFTER = 7;

    public function show(string $token): Response
    {
        $member = TripMember::query()->where('family_token', $token)->with('trip.owner')->firstOrFail();
        $trip = $member->trip;

        abort_unless($member->isTravelling(), 404);

        $visible = $trip->isActive() || ($trip->ended_at && $trip->ended_at->gt(now()->subDays(self::SHOW_DAYS_AFTER)));

        return response()
            ->view('family.show', [
                'trip' => $trip,
                'member' => $member,
                'visible' => $visible,
                'checkins' => $visible ? $trip->checkins()->with('member')->take(40)->get() : collect(),
                'groupSize' => $trip->members()->whereIn('status', ['active', 'invited'])->count(),
                'stay' => $visible ? ($trip->checkpoint['stay'] ?? null) : null,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
