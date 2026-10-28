<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripCollection;
use App\Models\TripMember;
use Illuminate\Support\Collection;

/**
 * Advance collections ("everyone send Rifat 2,000 before we leave"): who has
 * paid in, who has not, and what is left in the pot after the costs the
 * collector paid from it.
 *
 * There is no separate pot account. An advance is an ordinary payment to the
 * collector and a cost paid from the pot is a cost the collector paid, so the
 * trip balances always add up; whatever is left over at the end shows up as
 * the collector paying it back.
 */
class TripPot
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summaries(Trip $trip, ?TripMember $viewer = null): Collection
    {
        $collections = $trip->collections()->with('collector.user')->get();

        if ($collections->isEmpty()) {
            return collect();
        }

        $travellers = $trip->members()->with('user')->get()->filter(fn (TripMember $m) => $m->isTravelling() && $m->include_in_split);
        $payments = Settlement::query()
            ->whereIn('collection_id', $collections->pluck('id'))
            ->get(['id', 'collection_id', 'from_member_id', 'amount', 'confirmed_at'])
            ->groupBy('collection_id');

        return $collections->map(function (TripCollection $collection) use ($trip, $travellers, $payments, $viewer) {
            $paid = collect($payments[$collection->id] ?? []);
            $perPerson = (float) $collection->amount_per_person;

            $rows = $travellers
                ->reject(fn (TripMember $m) => $m->id === $collection->collector_member_id)
                ->map(function (TripMember $member) use ($paid, $perPerson) {
                    $mine = $paid->where('from_member_id', $member->id);
                    $due = round($perPerson * max(1, (int) $member->share_units), 2);
                    $confirmed = round((float) $mine->whereNotNull('confirmed_at')->sum('amount'), 2);
                    $pending = round((float) $mine->whereNull('confirmed_at')->sum('amount'), 2);
                    $left = max(0, round($due - $confirmed - $pending, 2));

                    return [
                        'member' => $member,
                        'due' => $due,
                        'paid' => $confirmed,
                        'pending' => $pending,
                        'left' => $left,
                        'status' => match (true) {
                            $confirmed >= $due - 0.009 => 'paid',
                            $left <= 0.009 => 'pending',
                            $confirmed + $pending > 0 => 'partial',
                            default => 'unpaid',
                        },
                    ];
                })
                ->sortBy(fn (array $row) => ['unpaid' => 0, 'partial' => 1, 'pending' => 2, 'paid' => 3][$row['status']])
                ->values();

            $target = round((float) $rows->sum('due'), 2);
            $collected = round((float) $rows->sum('paid'), 2);

            // Costs the collector logged since the collection started came out of the pot.
            $spent = round((float) $trip->expenses()
                ->where('trip_member_id', $collection->collector_member_id)
                ->where('created_at', '>=', $collection->created_at)
                ->sum('amount'), 2);

            return [
                'collection' => $collection,
                'collector' => $collection->collector,
                'rows' => $rows,
                'target' => $target,
                'collected' => $collected,
                'pending' => round((float) $rows->sum('pending'), 2),
                'percent' => $target > 0 ? min(100, round($collected / $target * 100, 1)) : 100.0,
                'paid_count' => $rows->where('status', 'paid')->count(),
                'spent' => $spent,
                'in_pot' => round($collected - $spent, 2),
                'mine' => $viewer ? $rows->first(fn (array $row) => $row['member']->id === $viewer->id) : null,
                'is_collector' => $viewer !== null && $viewer->id === $collection->collector_member_id,
            ];
        });
    }

    /**
     * The open collections a traveller still has to pay into.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dueFor(Trip $trip, TripMember $member): Collection
    {
        return $this->summaries($trip, $member)
            ->filter(fn (array $pot) => $pot['collection']->isOpen() && ($pot['mine']['left'] ?? 0) > 0.009)
            ->values();
    }
}
