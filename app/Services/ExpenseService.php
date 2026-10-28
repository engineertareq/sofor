<?php

namespace App\Services;

use App\Support\Phrase;
use App\Enums\SplitType;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Owns the lifecycle of an expense: who paid, who shares it, and how the
 * leftover poisha is spread so shares always add up to the exact amount.
 */
class ExpenseService
{
    public function __construct(
        private readonly SplitService $splitter,
        private readonly TripActivityLogger $logger,
        private readonly BudgetAlerts $budget,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Trip $trip, User $author, array $data): Expense
    {
        $this->guardTripIsOpen($trip);

        $payerId = (int) $data['trip_member_id'];
        $payer = $trip->members()->findOrFail($payerId);

        $expense = $trip->expenses()->create([
            'trip_member_id' => $payer->id,
            'created_by' => $author->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'spent_on' => $data['spent_on'] ?? now()->toDateString(),
            'split_type' => $data['split_type'] ?? SplitType::Equal->value,
            'notes' => $data['notes'] ?? null,
            'items' => $this->items($data),
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        $this->syncShares($trip, $expense, $data);

        $this->logger->log(
            $trip,
            'expense_added',
            Phrase::make(':author logged :expense for :amount', ['author' => $author->name, 'expense' => $expense->title, 'amount' => Phrase::money($expense->amount, $trip->currency)]),
            $author,
            $payer,
            ['expense_id' => $expense->id, 'amount' => (float) $expense->amount],
        );

        $this->logger->notifyMembers(
            $trip,
            Phrase::make('New cost in :trip', ['trip' => $trip->title]),
            Phrase::make(':expense - :amount added by :author', ['expense' => $expense->title, 'amount' => Phrase::money($expense->amount, $trip->currency), 'author' => $author->name]),
            'info',
            'receipt',
            [$author->id],
            meta: ['expense_id' => $expense->id],
        );

        $this->budget->check($trip);

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, User $author, array $data): Expense
    {
        $trip = $expense->trip;

        $this->guardTripIsOpen($trip);

        $expense->update([
            'trip_member_id' => $data['trip_member_id'] ?? $expense->trip_member_id,
            'title' => $data['title'] ?? $expense->title,
            'category' => $data['category'] ?? $expense->category,
            'amount' => $data['amount'] ?? $expense->amount,
            'spent_on' => $data['spent_on'] ?? $expense->spent_on,
            'split_type' => $data['split_type'] ?? $expense->split_type,
            'notes' => $data['notes'] ?? $expense->notes,
            'items' => isset($data['split_type']) ? $this->items($data) : $expense->items,
        ]);

        $this->syncShares($trip, $expense->refresh(), $data);

        $this->logger->log(
            $trip,
            'expense_updated',
            Phrase::make(':author updated :expense', ['author' => $author->name, 'expense' => $expense->title]),
            $author,
            $expense->payer,
            ['expense_id' => $expense->id],
        );

        $this->logger->notifyMembers(
            $trip,
            Phrase::make('Cost updated in :trip', ['trip' => $trip->title]),
            Phrase::make(':author edited :expense - now :amount', ['author' => $author->name, 'expense' => $expense->title, 'amount' => Phrase::money($expense->amount, $trip->currency)]),
            'warning',
            'receipt',
            [$author->id],
            meta: ['expense_id' => $expense->id],
        );

        $this->budget->check($trip);

        return $expense;
    }

    public function delete(Expense $expense, User $author): void
    {
        $this->guardTripIsOpen($expense->trip);

        $title = $expense->title;
        $amount = (float) $expense->amount;
        $trip = $expense->trip;

        // Shares are hard deleted so no phantom dues stay behind.
        $expense->shares()->delete();
        $expense->delete();

        $this->logger->log(
            $trip,
            'expense_deleted',
            Phrase::make(':author removed :title (:amount)', ['author' => $author->name, 'title' => $title, 'amount' => Phrase::money($amount, $trip->currency)]),
            $author,
            null,
            ['title' => $title, 'amount' => $amount],
        );

        $this->logger->notifyMembers(
            $trip,
            Phrase::make('Cost removed from :trip', ['trip' => $trip->title]),
            Phrase::make(':author deleted :title. Balances were recalculated.', ['author' => $author->name, 'title' => $title]),
            'warning',
            'trash',
            [$author->id],
        );

        $this->budget->check($trip);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncShares(Trip $trip, Expense $expense, array $data): void
    {
        // An itemised bill: each item is shared by whoever had it, and the
        // difference to the bill total (VAT, service, tips or a discount) is
        // spread in proportion to what each person ordered.
        $custom = match (true) {
            $expense->split_type === SplitType::Itemised && ! empty($expense->items) => $this->itemTotals($expense->items),
            $expense->split_type === SplitType::Custom && ! empty($data['custom_shares']) => $data['custom_shares'],
            default => null,
        };

        $members = $custom !== null
            ? $trip->members()->whereIn('id', array_keys($custom))->get()
            : $this->targetMembers($trip, $data['participant_ids'] ?? null);

        if ($members->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => __('Pick at least one traveller to share this cost with.'),
            ]);
        }

        if ($custom !== null) {
            $shares = $this->normaliseCustomShares($expense, $members, $custom);
        } else {
            $shares = $this->splitter->split($expense->amount, $members);
        }

        $expense->shares()->delete();

        foreach ($shares as $memberId => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $expense->shares()->create([
                'trip_member_id' => $memberId,
                'amount' => $amount,
                'units' => (int) ($members->firstWhere('id', $memberId)?->share_units ?? 1),
            ]);
        }
    }

    /**
     * @param  Collection<int, TripMember>  $members
     * @param  array<int, mixed>  $customShares
     * @return array<int, float>
     */
    private function normaliseCustomShares(Expense $expense, Collection $members, array $customShares): array
    {
        $total = (float) $expense->amount;
        $requested = [];
        $sum = 0.0;

        foreach ($members as $member) {
            $value = (float) ($customShares[$member->id] ?? 0);
            $requested[$member->id] = $value;
            $sum += $value;
        }

        if ($sum <= 0) {
            return $this->splitter->split($total, $members);
        }

        // Scale to the exact amount so custom splits can never drift.
        $scaled = [];
        $centsTotal = SplitService::toCents($total);
        $running = 0;

        foreach ($requested as $id => $value) {
            $cents = (int) round($centsTotal * ($value / $sum));
            $scaled[$id] = $cents;
            $running += $cents;
        }

        $diff = $centsTotal - $running;
        if ($diff !== 0 && $scaled !== []) {
            $first = array_key_first($scaled);
            $scaled[$first] += $diff;
        }

        return array_map(fn (int $cents) => SplitService::toAmount($cents), $scaled);
    }

    /**
     * The line items of an itemised split, tidied. Null for any other split.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{name: string, price: float, members: array<int, int>}>|null
     */
    private function items(array $data): ?array
    {
        if (($data['split_type'] ?? null) !== SplitType::Itemised->value || empty($data['items'])) {
            return null;
        }

        return collect($data['items'])
            ->map(fn (array $item) => [
                'name' => trim((string) $item['name']),
                'price' => round((float) $item['price'], 2),
                'members' => array_values(array_unique(array_map('intval', (array) $item['members']))),
            ])
            ->filter(fn (array $item) => $item['price'] > 0 && $item['members'] !== [])
            ->values()
            ->all() ?: null;
    }

    /**
     * What each traveller ordered: every item split evenly between the people
     * who had it, to the poisha.
     *
     * @param  array<int, array{name: string, price: float, members: array<int, int>}>  $items
     * @return array<int, float>
     */
    private function itemTotals(array $items): array
    {
        $cents = [];

        foreach ($items as $item) {
            $members = $item['members'];
            $price = SplitService::toCents((float) $item['price']);
            $each = intdiv($price, count($members));
            $left = $price - $each * count($members);

            foreach ($members as $index => $id) {
                $cents[$id] = ($cents[$id] ?? 0) + $each + ($index < $left ? 1 : 0);
            }
        }

        return array_map(fn (int $value) => SplitService::toAmount($value), $cents);
    }

    /**
     * @param  array<int, mixed>|null  $ids
     * @return Collection<int, TripMember>
     */
    private function targetMembers(Trip $trip, ?array $ids): Collection
    {
        if (! empty($ids)) {
            return $trip->members()->whereIn('id', $ids)->get();
        }

        return $trip->participants()->get();
    }

    private function guardTripIsOpen(Trip $trip): void
    {
        if ($trip->isEnded()) {
            throw ValidationException::withMessages([
                'amount' => __('This trip has been ended, so costs can no longer be changed.'),
            ]);
        }
    }
}
