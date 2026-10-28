<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripActivity;
use App\Models\User;
use App\Services\SponsorShowcase;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SponsorShowcase $sponsors): View
    {
        $totalUsers = User::count();
        $activeUsers = User::active()->count();
        $newUserRows = User::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $signupSeries = collect(range(13, 0))->map(function (int $daysAgo) use ($newUserRows) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'label' => now()->subDays($daysAgo)->format('d M'),
                'short' => now()->subDays($daysAgo)->format('d'),
                'value' => (int) ($newUserRows[$date] ?? 0),
            ];
        });

        $tripCounts = [
            'total' => Trip::count(),
            'active' => Trip::active()->count(),
            'ended' => Trip::ended()->count(),
        ];

        $expenseTotals = [
            'count' => Expense::count(),
            'sum' => (float) Expense::sum('amount'),
            'avg' => (float) Expense::avg('amount'),
            'settlements' => (float) DB::table('settlements')->sum('amount'),
            'settlement_count' => DB::table('settlements')->count(),
        ];

        $categoryRows = Expense::query()
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $topTrips = Trip::query()
            ->with('owner')
            ->withCount(['expenses', 'members'])
            ->withSum('expenses', 'amount')
            // has() keeps this portable across MySQL and sqlite (tests).
            ->has('expenses')
            ->orderByDesc('expenses_sum_amount')
            ->take(6)
            ->get();

        // Portable month bucketing so the console also works on sqlite (tests).
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyRows = Trip::query()
            ->selectRaw($monthExpression.' as month, COUNT(*) as total')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthlySeries = collect(range(5, 0))->map(function (int $monthsAgo) use ($monthlyRows) {
            $key = now()->subMonths($monthsAgo)->format('Y-m');

            return [
                'label' => now()->subMonths($monthsAgo)->format('M'),
                'value' => (int) ($monthlyRows[$key] ?? 0),
            ];
        });

        return view('admin.dashboard', [
            'stats' => [
                'users' => $totalUsers,
                'active_users' => $activeUsers,
                'paused_users' => $totalUsers - $activeUsers,
                'new_users_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'new_users_30d' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'admins' => User::where('role', 'admin')->count(),
                'trips' => $tripCounts,
                'expenses' => $expenseTotals,
                'avg_trip_size' => round((float) DB::table('trip_members')->where('status', 'active')->count() / max(1, $tripCounts['total']), 1),
                'avg_per_person' => round(DB::table('expense_shares')->avg('amount') ?? 0, 2),
                'activities' => TripActivity::count(),
                'donations' => [
                    'raised' => (float) Donation::query()->completed()->sum('amount'),
                    'charged' => (float) Donation::query()->completed()->sum('total'),
                    'fees' => (float) Donation::query()->completed()->sum('charge'),
                    'supporters' => Donation::query()->completed()->count(),
                    'count' => Donation::query()->completed()->count(),
                    'pending' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
                ],
            ],
            'signupSeries' => $signupSeries,
            'monthlySeries' => $monthlySeries,
            'categoryRows' => $categoryRows,
            'topTrips' => $topTrips,
            'recentUsers' => User::latest()->take(6)->get(),
            'recentTrips' => Trip::with('owner')->withCount('members')->latest()->take(6)->get(),
            'recentActivity' => TripActivity::with(['trip', 'user'])->latest('id')->take(10)->get(),
            'sponsorRequests' => [
                'count' => $sponsors->unanswered(),
                'latest' => $sponsors->latestUnanswered(),
            ],
        ]);
    }
}
