<?php

namespace App\Http\Requests;

use App\Models\Tour;
use App\Models\Trip;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** A new organised tour, or changes to one: its details, seats and packages. */
class SaveTourRequest extends FormRequest
{
    public const MAX_PACKAGES = 8;

    public function authorize(): bool
    {
        $tour = $this->route('tour');

        return $tour === null || $this->user()->can('manage', $tour);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_phone' => $this->filled('contact_phone') ? Phone::normalize($this->input('contact_phone')) : null,
            'show_account' => $this->boolean('show_account'),
            // Rows left empty in the form are not packages.
            'packages' => collect($this->input('packages', []))
                ->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['price'] ?? null)))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'destination' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cover' => ['required', Rule::in(array_keys(Trip::COVERS))],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'meeting_point' => ['nullable', 'string', 'max:160'],
            'meeting_at' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'closes_on' => ['nullable', 'date'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],
            'show_account' => ['boolean'],
            'packages' => ['required', 'array', 'min:1', 'max:'.self::MAX_PACKAGES],
            'packages.*.id' => ['nullable', 'integer'],
            'packages.*.name' => ['required', 'string', 'max:80'],
            'packages.*.description' => ['nullable', 'string', 'max:160'],
            'packages.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'packages.*.seats' => ['required', 'integer', 'min:1', 'max:10'],
            'packages.*.advance' => ['nullable', 'numeric', 'min:0', 'lte:packages.*.price'],
        ];
    }

    public function messages(): array
    {
        return [
            'packages.required' => __('Add at least one package with a price.'),
            'packages.*.name.required' => __('Give every package a name.'),
            'packages.*.price.required' => __('Give every package a price.'),
            'packages.*.advance.lte' => __('An advance cannot be more than the price.'),
            'contact_phone.regex' => __('Enter a Bangladeshi mobile number.'),
            'ends_on.after_or_equal' => __('The tour cannot end before it starts.'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Tour|null $tour */
            $tour = $this->route('tour');

            if ($tour === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $kept = collect($this->input('packages'))->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $owned = $tour->packages()->pluck('id');

            if ($kept->diff($owned)->isNotEmpty()) {
                $validator->errors()->add('packages', __('That package is not on this tour.'));

                return;
            }

            // A package somebody already booked stays, so their booking keeps its name and price.
            if ($tour->packages()->whereNotIn('id', $kept)->whereHas('registrations')->exists()) {
                $validator->errors()->add('packages', __('A package with bookings cannot be removed. Change its name or price instead.'));
            }
        }];
    }
}
