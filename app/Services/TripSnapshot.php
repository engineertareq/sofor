<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Api\Present;
use App\Support\Poisha;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the app shows about one trip, in one answer: the trip, its
 * travellers and where each stands, every cost and payment, and what this
 * traveller may do. The app stores it whole and works from that copy when
 * there is no signal; `stamp` changes whenever anything in it does, so a
 * refresh that finds the same stamp downloads nothing.
 *
 * Every number comes from TripLedger, the same as the website.
 */
class TripSnapshot
{
    public function __construct(private readonly TripLedger $ledger) {}

    /** @return array<string, mixed> */
    public function for(Trip $trip, User $viewer): array
    {
        $trip->loadMissing('owner');
        $trip->loadCount('expenses')->loadSum('expenses', 'amount');

        $ledger = $this->ledger->summarize($trip, $viewer);

        /** @var \Illuminate\Support\Collection<int, TripMember> $members */
        $members = $ledger['members']->load('user.payoutMethods');
        $trip->setRelation('members', $members);

        $gate = Gate::forUser($viewer);
        $isLeader = $trip->user_id === $viewer->id;
        $canAddExpense = $gate->allows('addExpense', $trip);
        $canRecord = $gate->allows('recordSettlement', $trip);

        $expenses = $trip->expenses()->with('shares')->get();
        $settlements = $trip->settlements()->with(['from', 'to'])->get();

        return [
            'stamp' => $trip->activityStamp(),
            'trip' => Present::trip($trip, $viewer, $ledger['me']),
            'me' => [
                'member_id' => $ledger['me']['member']?->id,
                'due' => Poisha::of($ledger['me']['due'] ?? 0),
                'credit' => Poisha::of($ledger['me']['credit'] ?? 0),
            ],
            'can' => [
                'update' => $gate->allows('update', $trip),
                'delete' => $gate->allows('delete', $trip),
                'end' => $gate->allows('end', $trip),
                'leave' => ! $isLeader && $ledger['me']['member'] !== null,
                'manage_members' => $gate->allows('manageMembers', $trip),
                'add_expense' => $canAddExpense,
                'record_settlement' => $canRecord,
            ],
            'stats' => [
                'spent' => Poisha::of($ledger['stats']['spent']),
                'per_person' => Poisha::of($ledger['stats']['per_person']),
                'outstanding' => Poisha::of($ledger['stats']['outstanding']),
                'expense_count' => (int) $ledger['stats']['expense_count'],
                'travellers' => (int) $ledger['stats']['travellers'],
                'budget' => Poisha::orNull($ledger['budget']['total'] ?? null),
                'budget_used_percent' => $ledger['budget']['used'] ?? null,
            ],
            'categories' => collect($ledger['categories'])->map(fn (array $row) => [
                'category' => $row['category']->value,
                'amount' => Poisha::of($row['total']),
                'count' => $row['count'],
                'percent' => $row['percent'],
            ])->values()->all(),
            'members' => $members
                ->map(fn (TripMember $member) => Present::member($member, $viewer, $ledger['balances'][$member->id] ?? []))
                ->values()
                ->all(),
            // Who should pay whom to settle everything with the fewest payments.
            'suggestions' => collect($ledger['suggestions'])->map(fn (array $row) => [
                'from_member_id' => $row['from_id'],
                'to_member_id' => $row['to_id'],
                'amount' => Poisha::of($row['amount']),
            ])->values()->all(),
            'expenses' => $expenses
                ->map(fn (Expense $expense) => Present::expense($expense, $canAddExpense && ($isLeader || $expense->created_by === $viewer->id)))
                ->all(),
            'settlements' => $settlements
                ->map(fn (Settlement $settlement) => Present::settlement(
                    $settlement,
                    $settlement->isPending() && $gate->allows('confirmSettlement', [$trip, $settlement]),
                    $canRecord && $settlement->gateway === null && $settlement->gateway_trx_id === null && $settlement->batch === null
                        && ($isLeader || $settlement->recorded_by === $viewer->id),
                ))
                ->all(),
        ];
    }
}
