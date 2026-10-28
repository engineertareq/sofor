<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Enums\MemberStatus;
use App\Enums\SplitType;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\ReceiptService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user()?->can('addExpense', $trip) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'amount' => $this->input('amount'),
            'spent_on' => $this->input('spent_on') ?: now()->toDateString(),
            'participant_ids' => array_values(array_filter(array_map('intval', (array) $this->input('participant_ids', [])))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'trip_member_id' => ['required', 'integer'],
            'split_type' => ['required', Rule::enum(SplitType::class)],
            'participant_ids' => ['array'],
            'participant_ids.*' => ['integer'],
            'custom_shares' => ['nullable', 'array'],
            'custom_shares.*' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:40', Rule::requiredIf($this->input('split_type') === SplitType::Itemised->value)],
            'items.*.name' => ['required', 'string', 'max:60'],
            'items.*.price' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.members' => ['required', 'array', 'min:1'],
            'items.*.members.*' => ['integer'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.ReceiptService::MAX_KILOBYTES],
            'remove_receipt' => ['nullable', 'boolean'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Trip $trip */
            $trip = $this->route('trip');

            // Travellers still on the trip. Editing an older cost may keep the
            // people it already had, even if one of them has since been removed.
            $memberIds = $trip->members()->where('status', '!=', MemberStatus::Removed->value)->pluck('id')->all();

            if (($expense = $this->route('expense')) instanceof Expense && $expense->trip_id === $trip->id) {
                $memberIds = array_values(array_unique(array_merge(
                    $memberIds,
                    [(int) $expense->trip_member_id],
                    $expense->shares()->pluck('trip_member_id')->map(fn ($id) => (int) $id)->all(),
                )));
            }

            if (! in_array((int) $this->input('trip_member_id'), $memberIds, true)) {
                $validator->errors()->add('trip_member_id', __('Choose a traveller from this trip as the payer.'));
            }

            foreach ($this->input('participant_ids', []) as $id) {
                if (! in_array((int) $id, $memberIds, true)) {
                    $validator->errors()->add('participant_ids', __('One of the selected travellers is not part of this trip.'));
                    break;
                }
            }

            if ($this->input('split_type') === SplitType::Custom->value) {
                $shares = collect($this->input('custom_shares', []))
                    ->filter(fn ($value) => (float) $value > 0);

                if ($shares->isEmpty()) {
                    $validator->errors()->add('custom_shares', __('Enter at least one custom amount.'));
                }

                if ($shares->keys()->contains(fn ($id) => ! in_array((int) $id, $memberIds, true))) {
                    $validator->errors()->add('custom_shares', __('One of the custom amounts is for a traveller who is not on this trip.'));
                }
            }

            if ($this->input('split_type') === SplitType::Itemised->value) {
                foreach ((array) $this->input('items', []) as $item) {
                    foreach ((array) ($item['members'] ?? []) as $id) {
                        if (! in_array((int) $id, $memberIds, true)) {
                            $validator->errors()->add('items', __('One of the items is shared with a traveller who is not on this trip.'));

                            break 2;
                        }
                    }
                }
            }

            if ($trip->isEnded()) {
                $validator->errors()->add('amount', __('This trip has ended. Costs are locked.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => __('The amount must be more than zero.'),
            'spent_on.before_or_equal' => __('You cannot log a cost for a future date.'),
            'items.required' => __('Add the items on the bill, or pick another way to split.'),
            'items.*.name.required' => __('Name every item on the bill.'),
            'items.*.price.required' => __('Every item needs a price.'),
            'items.*.members.required' => __('Pick who had each item.'),
            'receipt.mimes' => __('The receipt must be a JPG, PNG or WebP photo.'),
            'receipt.max' => __('That photo is over 8 MB. Try a smaller one.'),
        ];
    }
}
