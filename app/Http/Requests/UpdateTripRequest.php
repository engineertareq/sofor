<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('trip')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'destination' => filled($this->input('destination')) ? trim((string) $this->input('destination')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'destination' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover' => ['required', Rule::in(array_keys(Trip::COVERS))],
            'icon' => ['nullable', Rule::in(\App\Support\Glyphs::TRIP)],
            'currency' => ['required', Rule::in(['BDT', 'USD', 'EUR', 'GBP', 'INR', 'AED', 'MYR', 'SGD', 'THB'])],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'budget_per_person' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'budget_mode' => ['nullable', Rule::in(['group', 'person'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Trip $trip */
            $trip = $this->route('trip');

            // Amounts are stored as plain numbers, so switching the currency once
            // money is logged would relabel every cost without converting it.
            $hasMoney = $trip->expenses()->exists() || $trip->settlements()->exists();

            if ($hasMoney && $this->input('currency') !== $trip->currency) {
                $validator->errors()->add('currency', __('The currency cannot change once costs or payments are logged - every amount would silently mean something else.'));
            }
        });
    }
}
