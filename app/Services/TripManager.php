<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Phrase;
use Illuminate\Support\Facades\DB;

/**
 * A trip's own lifecycle - created, edited, ended, left - for the website and
 * the app alike. Who may do each of these is decided before it gets here
 * (TripPolicy and the form requests).
 */
class TripManager
{
    public function __construct(
        private readonly TripLedger $ledger,
        private readonly TripActivityLogger $logger,
        private readonly TripInvitationService $invitations,
        private readonly BudgetAlerts $budgetAlerts,
    ) {}

    /**
     * Sofor is free: every account creates as many trips as it likes.
     *
     * @param  array<string, mixed>  $data  validated by StoreTripRequest
     */
    public function create(User $user, array $data): Trip
    {
        // One transaction, so a trip never exists without its leader and invites.
        return DB::transaction(function () use ($data, $user) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'destination' => $data['destination'] ?? null,
                'description' => $data['description'] ?? null,
                'cover' => $data['cover'],
                'emoji' => $data['icon'] ?? 'compass',
                'currency' => $data['currency'],
                ...Trip::budgetAttributes($data),
                'status' => 'active',
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'last_activity_at' => now(),
            ]);

            $this->invitations->ensureOwnerMember($trip, $user);

            $this->logger->log($trip, 'trip_created', Phrase::make(':user created the trip', ['user' => $user->name]), $user);

            foreach ($data['members'] ?? [] as $row) {
                $this->invitations->invite($trip, $user, $row);
            }

            return $trip;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated by UpdateTripRequest
     */
    public function update(Trip $trip, User $user, array $data): void
    {
        $trip->update([
            'title' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'description' => $data['description'] ?? null,
            'cover' => $data['cover'],
            'emoji' => $data['icon'] ?? $trip->icon,
            'currency' => $data['currency'],
            ...Trip::budgetAttributes($data),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);

        $this->logger->log($trip, 'trip_updated', Phrase::make(':user updated the trip details', ['user' => $user->name]), $user);

        $this->budgetAlerts->check($trip);
    }

    /** Costs are locked and everybody is told the final total. */
    public function end(Trip $trip, User $user): void
    {
        $trip->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
        ])->save();

        $summary = $this->ledger->summarize($trip, $user);

        $this->logger->log(
            $trip,
            'trip_ended',
            Phrase::make(':user ended the trip at :amount', ['user' => $user->name, 'amount' => Phrase::money($summary['stats']['spent'], $trip->currency)]),
            $user,
            null,
            ['spent' => $summary['stats']['spent']],
        );

        $this->logger->notifyMembers(
            $trip,
            Phrase::make(':trip has ended', ['trip' => $trip->title]),
            Phrase::make('Total spend :amount. Costs are now locked - settle the remaining dues.', ['amount' => Phrase::money($summary['stats']['spent'], $trip->currency)]),
            'warning',
            'lock',
            [$user->id],
        );
    }

    /** A traveller (never the leader) walks away from the trip. */
    public function leave(Trip $trip, TripMember $member, User $user): void
    {
        $hasActivity = $member->shares()->exists() || $trip->expenses()->where('trip_member_id', $member->id)->exists();

        if ($hasActivity) {
            // Keep the history intact, just stop including them going forward.
            $member->update([
                'status' => MemberStatus::Removed->value,
                'include_in_split' => false,
            ]);
        } else {
            $member->delete();
        }

        $this->logger->log($trip, 'member_left', Phrase::make(':user left the trip', ['user' => $user->name]), $user, null);
        $this->logger->notifyMembers($trip, Phrase::make(':user left :trip', ['user' => $user->name, 'trip' => $trip->title]), Phrase::make('Balances were recalculated.'), 'warning', 'users', [$user->id]);
    }

    /** A new invite link; the old one stops working at once. */
    public function rotateInvite(Trip $trip, User $user): void
    {
        $trip->forceFill(['invite_code' => Trip::freshInviteCode()])->save();

        $this->logger->log($trip, 'invite_rotated', Phrase::make(':user generated a new invite link', ['user' => $user->name]), $user);
    }

    /**
     * The leader changes how a traveller is counted in the split.
     *
     * @param  array<string, mixed>  $data  name, share_units, status; include_in_split only when it was sent
     */
    public function updateMember(Trip $trip, TripMember $member, User $user, array $data): void
    {
        $member->update([
            'name' => $data['name'] ?? $member->name,
            'share_units' => $data['share_units'] ?? $member->share_units,
            'include_in_split' => array_key_exists('include_in_split', $data)
                ? (bool) $data['include_in_split']
                : $member->include_in_split,
            // The leader's own row always stays in the trip.
            'status' => $member->isOwner() ? $member->status->value : ($data['status'] ?? $member->status->value),
        ]);

        $this->logger->log(
            $trip,
            'member_updated',
            Phrase::make(':user updated :member\'s split settings', ['user' => $user->name, 'member' => $member->name]),
            $user,
            $member,
            [
                'share_units' => $member->share_units,
                'include_in_split' => $member->include_in_split,
            ],
        );
    }

    /**
     * The leader takes a traveller off the trip. Somebody with costs to their
     * name stays in the history and only drops out of future splits.
     *
     * @return bool whether their past costs were kept
     */
    public function removeMember(Trip $trip, TripMember $member, User $user): bool
    {
        $name = $member->name;
        $hasHistory = $member->shares()->exists() || $trip->expenses()->where('trip_member_id', $member->id)->exists();

        if ($hasHistory) {
            $member->update([
                'status' => MemberStatus::Removed->value,
                'include_in_split' => false,
            ]);
        } else {
            $member->delete();
        }

        $this->logger->log($trip, 'member_removed', Phrase::make(':user removed :name', ['user' => $user->name, 'name' => $name]), $user, null);
        $this->logger->notifyMembers($trip, Phrase::make(':name left :trip', ['name' => $name, 'trip' => $trip->title]), Phrase::make('Balances were recalculated.'), 'warning', 'users', [$user->id]);

        return $hasHistory;
    }

    /** Nudge a traveller who has not opened the trip yet. */
    public function remindMember(Trip $trip, TripMember $member): void
    {
        $member->update([
            'status' => $member->isRegistered() ? MemberStatus::Active->value : MemberStatus::Invited->value,
            'invited_at' => now(),
        ]);

        if ($member->user !== null) {
            $this->logger->notifyUser(
                $member->user,
                $trip,
                Phrase::make('Reminder: :trip', ['trip' => $trip->title]),
                Phrase::make('You still have a pending split in this trip. Open it to see your dues.'),
                'info',
                'bell',
            );
        }
    }
}
