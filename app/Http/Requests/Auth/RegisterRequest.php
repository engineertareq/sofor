<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use App\Support\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => Username::normalize($this->input('username')),
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => filled($this->input('phone')) ? Phone::normalize((string) $this->input('phone')) : null,
            'contact_type' => $this->input('contact_type', 'email'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'username' => Username::rules(),
            'contact_type' => ['required', Rule::in(['email', 'phone'])],
            'email' => [
                'nullable',
                'required_if:contact_type,email',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => [
                'nullable',
                'required_if:contact_type,phone',
                'string',
                'max:24',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value !== null && ! Phone::isValid((string) $value)) {
                        $fail(__('Enter a valid Bangladeshi mobile number, for example 01712345678.'));
                    }
                },
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return Username::messages() + [
            'email.unique' => __('An account already uses this email. Try logging in instead.'),
            'phone.unique' => __('An account already uses this phone number. Try logging in instead.'),
            'terms.accepted' => __('Please accept the terms to continue.'),
        ];
    }
}
