<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\ExpenseShare;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out who paid what, what each traveller owes, and the shortest set of
 * transfers that gets everybody back to zero.
 */
class TripLedger
{
    public function __construct(private readonly SplitService $splitter) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(Trip $trip, ?User $viewer = null): array
    {
        /** @var Collection<int, TripMember> $members */
        $members = $trip->members()->with('user')->get();
        $memberIds = $members->pluck('id')->all();

        $paid = $this->sumExpensesByMember($trip, 'trip_member_id');
        $shares = $this->sumSharesByMember($trip);
        $sent = $this->sumSettlementsByMember($trip, 'from_member_id');
        $received = $this->sumSettlementsByMember($trip, 'to_member_id');
        $sentCounts = $this->countSettlementsByMember($trip, 'from_member_id');
        $receivedCounts = $this->countSettlementsByMember($trip, 'to_member_id');

        $balances = [];
        $totalSpent = 0.0;
        $totalPaid = 0.0;
        $totalShare = 0.0;

        foreach ($members as $member) {
            $id = $member->id;
            $memberPaid = $this->cents($paid[$id] ?? 0);
            $memberShare = $this->cents($shares[$id] ?? 0);
            $memberSent = $this->cents($sent[$id] ?? 0);
            $memberReceived = $this->cents($received[$id] ?? 0);

            $balance = round($memberPaid - $memberShare + $memberSent - $memberReceived, 2);

            $balances[$id] = [
                'member_id' => $id,
                'paid' => $memberPaid,
                'share' => $memberShare,
                'sent' => $memberSent,
                'received' => $memberReceived,
                'contributed' => $memberSent,
                'contribution_count' => (int) ($sentCounts[$id] ?? 0),
                'received_count' => (int) ($receivedCounts[$id] ?? 0),
                'balance' => $balance,
                'due' => $balance < -0.009 ? abs($balance) : 0.0,
                'credit' => $balance > 0.009 ? $balance : 0.0,
            ];

            $totalPaid += $memberPaid;
            $totalShare += $memberShare;
        }

        // Trip spend is the sum of every logged expense, including ones whose
        // payer was later removed from the trip.
        $totalSpent = round((float) $trip->expenses()->sum('amount'), 2);

        // Invited travellers count too: they were on the trip, their account is
        // just not linked yet. Only declined or removed rows are left out.
        $travellers = $members->filter(fn (TripMember $m) => $m->isTravelling());
        $participants = $travellers->filter(fn (TripMember $m) => $m->include_in_split);
        $perPerson = $participants->isNotEmpty() ? round($totalSpent / $participants->count(), 2) : 0.0;

        $settledMembers = $travellers
            ->filter(fn (TripMember $m) => abs($balances[$m->id]['balance'] ?? 0) < 0.01)
            ->count();
        $trackedMembers = $travellers;

        $units = (float) $participants->sum(fn (TripMember $m) => max(1, (int) $m->share_units));
        $budget = $this->budget($trip, $totalSpent, $units);

        $contributionTotal = round((float) $trip->settlements()->sum('amount'), 2);
        $contributionCount = $trip->settlements()->count();
        $suggestions = $this->suggestTransfers($balances, $members);

        return [
            'trip' => $trip,
            'members' => $members,
            'balances' => $balances,
            'stats' => [
                'spent' => $totalSpent,
                'expense_count' => $trip->expenses()->count(),
                'per_person' => $perPerson,
                'travellers' => $travellers->count(),
                'participants' => $participants->count(),
                'member_total' => $members->count(),
                'paid_total' => round($totalPaid, 2),
                'share_total' => round($totalShare, 2),
                // Contributions: money travellers actually handed over, however
                // many times they paid it in.
                'contribution_total' => $contributionTotal,
                'contribution_count' => $contributionCount,
                'contributors' => (int) $trip->settlements()->distinct()->count('from_member_id'),
                'settled_amount' => $contributionTotal,
                'settlement_count' => $contributionCount,
                'budget' => $budget['total'],
                'budget_used' => $budget['used'],
                'avg_expense' => $trip->expenses()->count() > 0 ? round($totalSpent / $trip->expenses()->count(), 2) : 0.0,
                'outstanding' => round(array_sum(array_column($balances, 'due')), 2),
            ],
            'progress' => [
                'settled' => $trackedMembers->count() > 0
                    ? round($settledMembers / $trackedMembers->count() * 100, 1)
                    : 100.0,
                'settled_members' => $settledMembers,
                'total_members' => $trackedMembers->count(),
            ],
            'budget' => $budget,
            'categories' => $this->categoryBreakdown($trip, $totalSpent),
            'suggestions' => $suggestions,
            'me' => $this->viewerSummary($members, $balances, $viewer, $suggestions, $budget),
            'member_ids' => $memberIds,
        ];
    }

    /**
     * Where should this traveller send money next? Used to prefill the
     * contribution form: their own outstanding due, if they have one.
     *
     * @return array<string, mixed>|null
     */
    public function suggestionFor(Trip $trip, ?int $memberId = null): ?array
    {
        if ($memberId === null) {
            return null;
        }

        foreach ($this->summarize($trip)['suggestions'] as $suggestion) {
            if ($suggestion['from_id'] === $memberId) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * The traveller who has fronted the most money and should therefore receive
     * a contribution that was not aimed at anybody in particular.
     *
     * @return array<string, mixed>|null
     */
    public function biggestCreditor(Trip $trip, ?int $excludeMemberId = null): ?array
    {
        $data = $this->summarize($trip);

        $row = collect($data['balances'])
            ->filter(fn (array $balance) => $balance['credit'] > 0.009 && $balance['member_id'] !== $excludeMemberId)
            ->sortByDesc('credit')
            ->first();

        if ($row === null) {
            return null;
        }

        $member = $data['members']->firstWhere('id', $row['member_id']);

        return $member === null ? null : ['member' => $member, 'credit' => $row['credit']];
    }

    /**
     * The budget meter: how much of the plan is used, what is left, and where
     * spending lands by the last day at the pace so far.
     *
     * @return array<string, mixed>
     */
    private function budget(Trip $trip, float $spent, float $units): array
    {
        $total = $trip->budgetTotal($units);
        $perPerson = (float) $trip->budget_per_person > 0
            ? (float) $trip->budget_per_person
            : ($total !== null && $units > 0 ? round($total / $units, 2) : null);

        $forecast = null;
        $today = now()->startOfDay();

        if ($spent > 0 && $trip->isActive() && $trip->starts_on && $trip->ends_on && $trip->starts_on->lte($today) && $trip->ends_on->gt($today)) {
            $elapsed = $trip->starts_on->diffInDays($today) + 1;
            $length = $trip->starts_on->diffInDays($trip->ends_on) + 1;
            $forecast = round($spent / $elapsed * $length, 2);
        }

        $used = $total ? round($spent / $total * 100, 1) : null;

        return [
            'total' => $total,
            'per_person' => $perPerson,
            'mode' => (float) $trip->budget_per_person > 0 ? 'person' : ($total !== null ? 'group' : null),
            'units' => $units,
            'spent' => $spent,
            'used' => $used,
            'left' => $total !== null ? round($total - $spent, 2) : null,
            'forecast' => $forecast,
            'forecast_over' => $forecast !== null && $total !== null && $forecast > $total + 0.009,
            'tone' => $used === null ? null : ($used >= 100 ? 'negative' : ($used >= 80 ? 'warning' : 'positive')),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function countSettlementsByMember(Trip $trip, string $column): array
    {
        return Settlement::query()
            ->where('trip_id', $trip->id)
            ->selectRaw($column.' as member_id, COUNT(*) as total')
            ->groupBy($column)
            ->pluck('total', 'member_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function sumExpensesByMember(Trip $trip, string $column): array
    {
        return $trip->expenses()
            ->whereNotNull($column)
            ->selectRaw($column.' as member_id, SUM(amount) as total')
            ->groupBy($column)
            ->pluck('total', 'member_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function sumSharesByMember(Trip $trip): array
    {
        return ExpenseShare::query()
            ->whereHas('expense', fn ($query) => $query->where('trip_id', $trip->id))
            ->selectRaw('trip_member_id as member_id, SUM(amount) as total')
            ->groupBy('trip_member_id')
            ->pluck('total', 'member_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function sumSettlementsByMember(Trip $trip, string $column): array
    {
        return Settlement::query()
            ->where('trip_id', $trip->id)
            ->selectRaw($column.' as member_id, SUM(amount) as total')
            ->groupBy($column)
            ->pluck('total', 'member_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categoryBreakdown(Trip $trip, float $total): array
    {
        $rows = $trip->expenses()
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            // The relation sorts by date; the breakdown wants the biggest category first.
            ->reorder()
            ->orderByDesc('total')
            ->get();

        return $rows->map(function ($row) use ($total) {
            $value = $row->category;
            $category = $value instanceof ExpenseCategory
                ? $value
                : (ExpenseCategory::tryFrom((string) $value) ?? ExpenseCategory::Other);

            return [
                'category' => $category,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'total' => (float) $row->total,
                'count' => (int) $row->count,
                'percent' => $total > 0 ? round((float) $row->total / $total * 100, 1) : 0.0,
            ];
        })->all();
    }

    /**
     * Greedy debt simplification: creditors and debtors are matched largest
     * first, which keeps the number of real world transactions small.
     *
     * @param  array<int, array<string, mixed>>  $balances
     * @param  Collection<int, TripMember>  $members
     * @return array<int, array<string, mixed>>
     */
    public function suggestTransfers(array $balances, Collection $members): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($balances as $id => $row) {
            if ($row['balance'] < -0.009) {
                $debtors[] = ['id' => $id, 'amount' => abs($row['balance'])];
            } elseif ($row['balance'] > 0.009) {
                $creditors[] = ['id' => $id, 'amount' => $row['balance']];
            }
        }

        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $suggestions = [];
        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = min($debtors[$i]['amount'], $creditors[$j]['amount']);

            if ($amount > 0.009) {
                $from = $members->firstWhere('id', $debtors[$i]['id']);
                $to = $members->firstWhere('id', $creditors[$j]['id']);

                if ($from !== null && $to !== null) {
                    $suggestions[] = [
                        'from' => $from,
                        'to' => $to,
                        'from_id' => $from->id,
                        'to_id' => $to->id,
                        'amount' => round($amount, 2),
                    ];
                }
            }

            $debtors[$i]['amount'] -= $amount;
            $creditors[$j]['amount'] -= $amount;

            if ($debtors[$i]['amount'] <= 0.009) {
                $i++;
            }

            if ($creditors[$j]['amount'] <= 0.009) {
                $j++;
            }
        }

        return $suggestions;
    }

    /**
     * @param  Collection<int, TripMember>  $members
     * @param  array<int, array<string, mixed>>  $balances
     * @param  array<int, array<string, mixed>>  $suggestions
     * @return array<string, mixed>|null
     */
    private function viewerSummary(Collection $members, array $balances, ?User $viewer, array $suggestions = [], array $budget = []): ?array
    {
        if ($viewer === null) {
            return null;
        }

        $member = $members->firstWhere('user_id', $viewer->id);

        if ($member === null) {
            return [
                'member' => null,
                'balance' => 0.0,
                'due' => 0.0,
                'credit' => 0.0,
                'paid' => 0.0,
                'share' => 0.0,
                'contributed' => 0.0,
                'contribution_count' => 0,
                'next_payment' => null,
                'is_member' => false,
            ];
        }

        $nextPayment = null;

        foreach ($suggestions as $suggestion) {
            if ($suggestion['from_id'] === $member->id) {
                $nextPayment = $suggestion;

                break;
            }
        }

        // A couple counts as two people against a per-person budget.
        $myBudget = ($budget['per_person'] ?? null) !== null && $member->include_in_split
            ? round($budget['per_person'] * max(1, (int) $member->share_units), 2)
            : null;

        return array_merge($balances[$member->id] ?? [], [
            'member' => $member,
            'is_member' => true,
            'is_owner' => $member->isOwner(),
            'next_payment' => $nextPayment,
            'budget' => $myBudget,
            'budget_used' => $myBudget ? round(($balances[$member->id]['share'] ?? 0) / $myBudget * 100, 1) : null,
        ]);
    }

    private function cents(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }
}
