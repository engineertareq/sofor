<?php

namespace App\Http\Requests;

use App\Models\Trip;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $members = collect($this->input('members', []))
            ->map(function ($member) {
                return [
                    'name' => trim((string) ($member['name'] ?? '')),
                    'email' => filled($member['email'] ?? null) ? strtolower(trim((string) $member['email'])) : null,
                    'phone' => filled($member['phone'] ?? null) ? Phone::normalize((string) $member['phone']) : null,
                    'share_units' => max(1, (int) ($member['share_units'] ?? 1)),
                ];
            })
            ->filter(fn (array $member) => $member['name'] !== '' || $member['email'] !== null || $member['phone'] !== null)
            ->values()
            ->all();

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'destination' => filled($this->input('destination')) ? trim((string) $this->input('destination')) : null,
            'members' => $members,
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
            'members' => ['array', 'max:40'],
            'members.*.name' => ['nullable', 'string', 'max:80'],
            'members.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'members.*.phone' => ['nullable', 'string', 'max:24'],
            'members.*.share_units' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $members = $this->input('members', []);

            foreach ($members as $index => $member) {
                if (($member['email'] ?? null) === null && ($member['phone'] ?? null) === null && ($member['name'] ?? '') === '') {
                    $validator->errors()->add("members.$index.name", __('Add a name, email or phone for this traveller.'));

                    continue;
                }

                if (($member['phone'] ?? null) !== null && ! Phone::isValid($member['phone'])) {
                    $validator->errors()->add("members.$index.phone", __('That mobile number does not look right.'));
                }
            }

            $emails = collect($members)->pluck('email')->filter()->map(fn ($value) => strtolower((string) $value));
            $phones = collect($members)->pluck('phone')->filter();

            $hasDuplicateEmail = $emails->count() !== $emails->unique()->count();
            $hasDuplicatePhone = $phones->count() !== $phones->unique()->count();

            if ($hasDuplicateEmail || $hasDuplicatePhone) {
                $validator->errors()->add('members', __('Looks like the same traveller appears twice. Remove the duplicate row.'));
            }

            // A traveller row may not point at the leader's own account, and two
            // rows may not describe the same account with different details.
            $matchedIds = [];

            foreach ($members as $index => $member) {
                $memberEmail = $member['email'] ?? null;
                $memberPhone = $member['phone'] ?? null;

                if ($memberEmail === null && $memberPhone === null) {
                    continue;
                }

                $matchedUser = User::findByContact($memberEmail, $memberPhone);

                if ($matchedUser === null) {
                    continue;
                }

                if ($matchedUser->id === $this->user()?->id) {
                    $validator->errors()->add("members.$index.email", __('You are the trip leader already, no need to list yourself as a traveller.'));

                    continue;
                }

                if (in_array($matchedUser->id, $matchedIds, true)) {
                    $validator->errors()->add("members.$index.email", __('This traveller appears more than once. Remove the duplicate row.'));

                    continue;
                }

                $matchedIds[] = $matchedUser->id;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('Give your trip a name so travellers recognise it.'),
            'cover.in' => __('Pick one of the available cover styles.'),
        ];
    }
}
