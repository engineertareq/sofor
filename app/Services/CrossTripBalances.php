<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Where a traveller stands with each friend over every trip they shared:
 * "you owe Asha 300 on Sajek, she owes you 500 on Sylhet - net, she owes you
 * 200". Built from each trip's own settle up suggestions, per currency.
 */
class CrossTripBalances
{
    public const MAX_TRIPS = 80;

    public function __construct(private readonly TripLedger $ledger) {}

    /**
     * One row per friend and currency, the biggest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user): Collection
    {
        $rows = [];

        foreach ($this->trips($user) as $trip) {
            $summary = $this->ledger->summarize($trip);
            $mine = $summary['members']->firstWhere('user_id', $user->id);

            if ($mine === null) {
                continue;
            }

            foreach ($summary['suggestions'] as $suggestion) {
                if ($suggestion['from_id'] === $mine->id) {
                    [$other, $sign] = [$suggestion['to'], -1];
                } elseif ($suggestion['to_id'] === $mine->id) {
                    [$other, $sign] = [$suggestion['from'], 1];
                } else {
                    continue;
                }

                /** @var TripMember $other */
                $key = ($other->user_id ? 'u'.$other->user_id : 'm'.$other->id).':'.$trip->currency;

                $rows[$key] ??= [
                    'key' => $key,
                    'user' => $other->user,
                    'member' => $other,
                    'name' => $other->user?->name ?? $other->name,
                    'currency' => $trip->currency,
                    'net' => 0.0,
                    'items' => [],
                ];

                $rows[$key]['net'] = round($rows[$key]['net'] + $sign * $suggestion['amount'], 2);
                $rows[$key]['items'][] = [
                    'trip' => $trip,
                    'amount' => $suggestion['amount'],
                    'you_owe' => $sign < 0,
                    'from' => $suggestion['from'],
                    'to' => $suggestion['to'],
                ];
            }
        }

        return collect($rows)
            ->map(fn (array $row) => $row + ['nettable' => $row['user'] !== null && count($row['items']) > 0])
            ->sortByDesc(fn (array $row) => abs($row['net']))
            ->values();
    }

    /**
     * The open items between two people in one currency, worked out fresh.
     *
     * @return array<string, mixed>|null
     */
    public function between(User $user, User $friend, string $currency): ?array
    {
        return $this->forUser($user)->first(fn (array $row) => $row['user']?->id === $friend->id && $row['currency'] === $currency);
    }

    /** @return Collection<int, Trip> */
    private function trips(User $user): Collection
    {
        return Trip::query()
            ->visibleTo($user)
            ->latest('last_activity_at')
            ->limit(self::MAX_TRIPS)
            ->get();
    }
}
