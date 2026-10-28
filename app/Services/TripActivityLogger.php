<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripActivity;
use App\Models\TripMember;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Support\Phrase;
use Illuminate\Support\Facades\Notification;

/**
 * Writes the trip timeline and fans notifications out to everybody who should
 * know that something changed.
 */
class TripActivityLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        Trip $trip,
        string $type,
        Phrase|string $description,
        ?User $actor = null,
        ?TripMember $member = null,
        array $meta = [],
    ): TripActivity {
        // The timeline is read in each traveller's language: keep the sentence
        // itself, plus the English text for anything that reads the column.
        if ($description instanceof Phrase) {
            $meta['phrase'] = $description->toArray();
        }

        $activity = $trip->activities()->create([
            'user_id' => $actor?->id,
            'trip_member_id' => $member?->id,
            'type' => $type,
            'description' => $description instanceof Phrase ? $description->render('en') : $description,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        $trip->markActivity();

        return $activity;
    }

    /**
     * @param  array<int, int>  $exceptUserIds
     * @param  array<string, mixed>  $meta
     */
    public function notifyMembers(
        Trip $trip,
        Phrase|string $title,
        Phrase|string $body,
        string $tone = 'info',
        string $icon = 'bell',
        array $exceptUserIds = [],
        ?string $url = null,
        array $meta = [],
    ): void {
        $recipients = $trip->members()
            ->whereNotNull('user_id')
            ->where('status', '!=', 'removed')
            ->whereNotIn('user_id', $exceptUserIds)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TripNotification(
            title: $title,
            body: $body,
            url: $url ?? route('trips.show', $trip),
            tone: $tone,
            icon: $icon,
            tripId: $trip->id,
            meta: $meta,
        ));
    }

    public function notifyUser(User $user, Trip $trip, Phrase|string $title, Phrase|string $body, string $tone = 'info', string $icon = 'bell'): void
    {
        $user->notify(new TripNotification(
            title: $title,
            body: $body,
            url: route('trips.show', $trip),
            tone: $tone,
            icon: $icon,
            tripId: $trip->id,
        ));
    }
}
