<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user()?->can('view', $trip) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'method' => $this->input('method', PaymentMethod::Bkash->value),
            'settled_on' => $this->input('settled_on') ?: now()->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Either side can be left out: the controller works out who is paying
            // and who should receive it from the current balances.
            'from_member_id' => ['nullable', 'integer'],
            'to_member_id' => ['nullable', 'integer', 'different:from_member_id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Netted])],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:200'],
            'settled_on' => ['required', 'date', 'before_or_equal:today'],
            'collection_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Trip $trip */
            $trip = $this->route('trip');
            $memberIds = $trip->members()->pluck('id')->all();

            // An advance goes to that collection's collector, while it is open.
            if ($this->filled('collection_id') && ! $trip->collections()->whereKey((int) $this->input('collection_id'))->whereNull('closed_at')->exists()) {
                $validator->errors()->add('collection_id', __('That collection is closed. Record it as an ordinary payment instead.'));
            }

            foreach (['from_member_id', 'to_member_id'] as $field) {
                if ($this->input($field) !== null && ! in_array((int) $this->input($field), $memberIds, true)) {
                    $validator->errors()->add($field, __('Choose travellers from this trip.'));
                }
            }
        });
    }
}
