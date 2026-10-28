<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Money the community has given to support Sofor. Completed gifts are the only ones that
 * count towards the running total; pending rows are payments that never came
 * back from bKash and can be ignored.
 */
class DonationController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'completed');

        $donations = Donation::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $completed = Donation::query()->completed();

        // The gateway fee is absorbed by Sofor rather than billed to the donor,
        // so `raised` is the gross gifts and `net` is what actually lands.
        $raised = (float) (clone $completed)->sum('amount');
        $fees = (float) (clone $completed)->sum('charge');

        return view('admin.donations.index', [
            'donations' => $donations,
            'status' => $status,
            'totals' => [
                'raised' => $raised,
                'fees' => $fees,
                'net' => round($raised - $fees, 2),
                'supporters' => (clone $completed)->count(),
                'average' => (float) (clone $completed)->avg('amount'),
                'pending' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
            ],
            'counts' => [
                'completed' => Donation::query()->where('status', Donation::STATUS_COMPLETED)->count(),
                'pending' => Donation::query()->where('status', Donation::STATUS_PENDING)->count(),
                'cancelled' => Donation::query()->where('status', Donation::STATUS_CANCELLED)->count(),
                'failed' => Donation::query()->where('status', Donation::STATUS_FAILED)->count(),
            ],
            'bkashEnabled' => app(\App\Services\BkashService::class)->canTakePayments(),
        ]);
    }
}
