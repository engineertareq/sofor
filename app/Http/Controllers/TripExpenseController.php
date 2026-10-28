<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\ExpenseService;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TripExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ReceiptService $receipts,
    ) {}

    public function store(StoreExpenseRequest $request, Trip $trip): RedirectResponse|JsonResponse
    {
        // A cost queued offline and sent twice (the first reply was lost) is only logged once.
        if ($request->filled('client_uuid')) {
            $existing = $trip->expenses()->where('client_uuid', $request->input('client_uuid'))->first();

            if ($existing !== null) {
                $message = __(':existing was already saved.', ['existing' => $existing->title]);

                return $request->wantsJson()
                    ? response()->json(['message' => $message, 'expense_id' => $existing->id, 'duplicate' => true])
                    : back()->with('status', $message);
            }
        }

        $receipt = $request->hasFile('receipt') ? $this->receipts->prepare($request->file('receipt')) : null;

        $expense = $this->expenses->create($trip, $request->user(), $request->validated());

        $message = __(':expense added - :amount split across the trip.', ['expense' => $expense->title, 'amount' => \App\Support\Money::format($expense->amount, $trip->currency)]);

        if ($receipt !== null && ! $this->receipts->attach($expense, $receipt)) {
            $message .= ' '.__('The receipt photo could not be saved - add it again from the cost in a moment.');
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'expense_id' => $expense->id]);
        }

        return back()->with('status', $message);
    }

    public function update(StoreExpenseRequest $request, Trip $trip, Expense $expense): RedirectResponse
    {
        $this->ensureBelongsToTrip($trip, $expense);

        $this->authorize('modifyExpense', [$trip, $expense]);

        $receipt = $request->hasFile('receipt') ? $this->receipts->prepare($request->file('receipt')) : null;

        $this->expenses->update($expense, $request->user(), $request->validated());

        $message = __(':expense updated and balances recalculated.', ['expense' => $expense->title]);

        if ($receipt !== null) {
            if (! $this->receipts->attach($expense, $receipt)) {
                $message .= ' '.__('The receipt photo could not be saved - try again in a moment.');
            }
        } elseif ($request->boolean('remove_receipt')) {
            $this->receipts->remove($expense);
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, Trip $trip, Expense $expense): RedirectResponse
    {
        $this->ensureBelongsToTrip($trip, $expense);

        $this->authorize('modifyExpense', [$trip, $expense]);

        $this->expenses->delete($expense, $request->user());
        $this->receipts->remove($expense);

        return back()->with('status', __('Cost removed and balances recalculated.'));
    }

    private function ensureBelongsToTrip(Trip $trip, Expense $expense): void
    {
        abort_unless($expense->trip_id === $trip->id, 404);
    }
}
