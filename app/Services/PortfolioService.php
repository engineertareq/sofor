<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseShare;
use App\Models\Settlement;
use App\Models\TripMember;
use App\Models\User;

/**
 * Cross-trip view of what a user owes and is owed. Keeps the dashboard and the
 * trips index to a handful of aggregate queries instead of one ledger per trip.
 */
class PortfolioService
{
    /**
     * @return array{trips: array<int, array<string, float|int>>, totals: array<string, float|int>}
     */
    public function forUser(User $user): array
    {
        $memberIds = TripMember::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'removed')
            ->pluck('id')
            ->all();

        $empty = ['due' => 0.0, 'credit' => 0.0, 'paid' => 0.0, 'share' => 0.0, 'settled_out' => 0.0, 'settled_in' => 0.0, 'balance' => 0.0];

        if ($memberIds === []) {
            return ['trips' => [], 'totals' => $empty];
        }

        $paid = Expense::query()
            ->whereIn('trip_member_id', $memberIds)
            ->selectRaw('trip_id, SUM(amount) as total')
            ->groupBy('trip_id')
            ->pluck('total', 'trip_id');

        $shares = ExpenseShare::query()
            ->join('expenses', 'expenses.id', '=', 'expense_shares.expense_id')
            ->whereIn('expense_shares.trip_member_id', $memberIds)
            ->whereNull('expenses.deleted_at')
            ->selectRaw('expenses.trip_id as trip_id, SUM(expense_shares.amount) as total')
            ->groupBy('expenses.trip_id')
            ->pluck('total', 'trip_id');

        $sent = Settlement::query()
            ->whereIn('from_member_id', $memberIds)
            ->selectRaw('trip_id, SUM(amount) as total')
            ->groupBy('trip_id')
            ->pluck('total', 'trip_id');

        $received = Settlement::query()
            ->whereIn('to_member_id', $memberIds)
            ->selectRaw('trip_id, SUM(amount) as total')
            ->groupBy('trip_id')
            ->pluck('total', 'trip_id');

        $tripIds = collect($paid->keys())
            ->merge($shares->keys())
            ->merge($sent->keys())
            ->merge($received->keys())
            ->unique()
            ->values();

        $rows = [];
        $totals = $empty;

        foreach ($tripIds as $tripId) {
            $paidValue = round((float) ($paid[$tripId] ?? 0), 2);
            $shareValue = round((float) ($shares[$tripId] ?? 0), 2);
            $sentValue = round((float) ($sent[$tripId] ?? 0), 2);
            $receivedValue = round((float) ($received[$tripId] ?? 0), 2);
            $balance = round($paidValue - $shareValue + $sentValue - $receivedValue, 2);

            $rows[$tripId] = [
                'due' => $balance < -0.009 ? abs($balance) : 0.0,
                'credit' => $balance > 0.009 ? $balance : 0.0,
                'paid' => $paidValue,
                'share' => $shareValue,
                'settled_out' => $sentValue,
                'settled_in' => $receivedValue,
                'balance' => $balance,
            ];

            $totals['due'] = round($totals['due'] + $rows[$tripId]['due'], 2);
            $totals['credit'] = round($totals['credit'] + $rows[$tripId]['credit'], 2);
            $totals['paid'] = round($totals['paid'] + $paidValue, 2);
            $totals['share'] = round($totals['share'] + $shareValue, 2);
            $totals['settled_out'] = round($totals['settled_out'] + $sentValue, 2);
            $totals['settled_in'] = round($totals['settled_in'] + $receivedValue, 2);
        }

        $totals['balance'] = round($totals['credit'] - $totals['due'], 2);

        return ['trips' => $rows, 'totals' => $totals];
    }
}
