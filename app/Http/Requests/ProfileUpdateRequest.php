<?php

namespace App\Http\Requests;

use App\Support\Phone;
use App\Support\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => Username::normalize($this->input('username')),
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => filled($this->input('phone')) ? Phone::normalize((string) $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'username' => Username::rules($user),
            // Only sent by the traveller form; admin accounts have no public profile.
            'profile_public' => ['sometimes', 'boolean'],
            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:24',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && ! Phone::isValid((string) $value)) {
                        $fail(__('Enter a valid Bangladeshi mobile number, for example 01712345678.'));
                    }
                },
                Rule::unique('users', 'phone')->ignore($user?->id),
            ],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return Username::messages();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('email') === null && $this->input('phone') === null) {
                $validator->errors()->add('email', __('Keep at least one of email or phone so travellers can reach you.'));
            }
        });
    }
}
