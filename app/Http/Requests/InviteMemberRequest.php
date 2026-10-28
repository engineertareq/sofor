<?php

namespace App\Http\Requests;

use App\Models\Trip;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user()?->can('manageMembers', $trip) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => filled($this->input('phone')) ? Phone::normalize((string) $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:24'],
            'share_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'include_in_split' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Trip $trip */
            $trip = $this->route('trip');

            if ($this->input('name') === '' && $this->input('email') === null && $this->input('phone') === null) {
                $validator->errors()->add('name', __('Add a name, email or phone for the traveller.'));
            }

            if ($this->input('phone') !== null && ! Phone::isValid($this->input('phone'))) {
                $validator->errors()->add('phone', __('Enter a valid Bangladeshi mobile number, for example 01712345678.'));
            }

            $email = $this->input('email');
            $phone = $this->input('phone');

            if ($trip->isEnded()) {
                $validator->errors()->add('name', __('This trip has ended, so travellers can no longer be added.'));

                return;
            }

            if ($email === null && $phone === null) {
                return;
            }

            // Already on the trip with these exact contact details?
            $duplicate = $trip->members()
                ->where(function ($query) use ($email, $phone) {
                    if ($email !== null) {
                        $query->orWhere('email', $email);
                    }

                    if ($phone !== null) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->first();

            if ($duplicate !== null) {
                $validator->errors()->add('email', $duplicate->name.' is already on this trip ('.$duplicate->contactLabel().').');

                return;
            }

            // Or is this account already travelling under different details?
            $matchedUser = User::findByContact($email, $phone);

            if ($matchedUser === null) {
                return;
            }

            if ($matchedUser->id === $this->user()?->id) {
                $validator->errors()->add('email', __('You already lead this trip, so you cannot add yourself as a traveller.'));

                return;
            }

            if ($trip->members()->where('user_id', $matchedUser->id)->exists()) {
                $validator->errors()->add('email', __('That Sofor account is already travelling on this trip.'));
            }
        });
    }
}
