<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecordSettlementRequest;
use App\Http\Requests\Api\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Trip;
use App\Services\ContributionService;
use App\Services\ExpenseService;
use App\Services\ReceiptService;
use App\Services\TripActivityLogger;
use App\Services\TripSnapshot;
use App\Support\Money;
use App\Support\Phrase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The money on a trip: costs and the payments between travellers.
 *
 * A cost or payment typed with no signal is sent later, and may be sent twice
 * when the first answer was lost. Each carries a `client_uuid` the app made
 * up; the second arrival finds the first and changes nothing.
 *
 * Every answer is the trip's whole snapshot, with `id` naming the row that
 * was written.
 */
class LedgerController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ReceiptService $receipts,
        private readonly ContributionService $contributions,
        private readonly TripSnapshot $snapshots,
    ) {}

    public function storeExpense(StoreExpenseRequest $request, Trip $trip): JsonResponse
    {
        if ($request->filled('client_uuid')) {
            $existing = $trip->expenses()->where('client_uuid', $request->input('client_uuid'))->first();

            if ($existing !== null) {
                return $this->snapshot($request, $trip, __(':existing was already saved.', ['existing' => $existing->title]), $existing->id);
            }
        }

        $receipt = $request->hasFile('receipt') ? $this->receipts->prepare($request->file('receipt')) : null;

        $expense = $this->expenses->create($trip, $request->user(), $request->validated());

        $message = __(':expense added - :amount split across the trip.', ['expense' => $expense->title, 'amount' => Money::format($expense->amount, $trip->currency)]);

        if ($receipt !== null && ! $this->receipts->attach($expense, $receipt)) {
            $message .= ' '.__('The receipt photo could not be saved - add it again from the cost in a moment.');
        }

        return $this->snapshot($request, $trip, $message, $expense->id, 201);
    }

    public function updateExpense(StoreExpenseRequest $request, Trip $trip, Expense $expense): JsonResponse
    {
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

        return $this->snapshot($request, $trip, $message, $expense->id);
    }

    public function destroyExpense(Request $request, Trip $trip, Expense $expense): JsonResponse
    {
        $this->authorize('modifyExpense', [$trip, $expense]);

        $this->expenses->delete($expense, $request->user());
        $this->receipts->remove($expense);

        return $this->snapshot($request, $trip, __('Cost removed and balances recalculated.'));
    }

    public function storeSettlement(RecordSettlementRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('recordSettlement', $trip);

        if ($request->filled('client_uuid')) {
            $existing = $trip->settlements()->where('client_uuid', $request->input('client_uuid'))->first();

            if ($existing !== null) {
                return $this->snapshot($request, $trip, __('That payment was already saved.'), $existing->id);
            }
        }

        $settlement = $this->contributions->recordAs($trip, $request->user(), $request->validated());
        $amount = Money::format($settlement->amount, $trip->currency);

        return $this->snapshot($request, $trip, $settlement->isPending()
            ? __('Recorded :amount from :from to :to. :to is asked to confirm it arrived.', ['amount' => $amount, 'from' => $settlement->from->name, 'to' => $settlement->to->name])
            : __('Contribution of :amount recorded for :from. Dues across the trip are updated.', ['amount' => $amount, 'from' => $settlement->from->name]), $settlement->id, 201);
    }

    /** The receiver saw the money arrive. */
    public function confirmSettlement(Request $request, Trip $trip, Settlement $settlement): JsonResponse
    {
        $this->authorize('confirmSettlement', [$trip, $settlement]);

        $this->contributions->confirm($settlement, $request->user());

        return $this->snapshot($request, $trip, __('Payment confirmed. Balances are up to date.'), $settlement->id);
    }

    /** The receiver says the money never arrived. */
    public function rejectSettlement(Request $request, Trip $trip, Settlement $settlement): JsonResponse
    {
        $this->authorize('confirmSettlement', [$trip, $settlement]);

        $from = $settlement->from?->name;
        $this->contributions->reject($settlement, $request->user());

        return $this->snapshot($request, $trip, __('Marked as not received. :from has been told.', ['from' => $from]));
    }

    public function destroySettlement(Request $request, Trip $trip, Settlement $settlement, TripActivityLogger $logger): JsonResponse
    {
        $this->authorize('deleteSettlement', [$trip, $settlement]);

        $amount = Money::format($settlement->amount, $trip->currency);
        $settlement->delete();

        $logger->log(
            $trip,
            'settlement_deleted',
            Phrase::make(':user removed a :amount contribution record', ['user' => $request->user()->name, 'amount' => $amount]),
            $request->user(),
        );

        return $this->snapshot($request, $trip, __('Contribution removed. Dues recalculated for the whole trip.'));
    }

    private function snapshot(Request $request, Trip $trip, string $message, ?int $id = null, int $status = 200): JsonResponse
    {
        return response()->json(
            $this->snapshots->for($trip->refresh(), $request->user()) + ['message' => $message, 'id' => $id],
            $status,
        );
    }
}
