<?php

namespace App\Http\Controllers;

use App\Services\TripEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The cost estimate shown on the new-trip form while the destination and
 * dates are filled in.
 */
class TripEstimateController extends Controller
{
    public function __invoke(Request $request, TripEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'destination' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        $days = null;

        if (filled($data['starts_on'] ?? null) && filled($data['ends_on'] ?? null)) {
            $start = Carbon::parse($data['starts_on']);
            $end = Carbon::parse($data['ends_on']);
            $days = $end->gte($start) ? (int) $start->diffInDays($end) + 1 : null;
        }

        return response()->json([
            'estimate' => $estimator->estimate($data['destination'] ?? null, strtoupper($data['currency'] ?? 'BDT'), $days),
        ]);
    }
}
