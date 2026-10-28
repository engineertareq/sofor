<?php

namespace App\Http\Requests;

use App\Models\Sponsor;
use App\Support\WebAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The "become a sponsor" form on /sponsors. Anyone can send it. */
class StoreSponsorInquiryRequest extends FormRequest
{
    /** A field people never see. Bots fill every input, so a value here means a bot. */
    public const HONEYPOT = 'nickname';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'website' => WebAddress::normalize($this->input('website')),
            'tier' => filled($this->input('tier')) ? $this->input('tier') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'company' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'tier' => ['nullable', Rule::in(array_keys(Sponsor::TIERS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('Tell us who to reply to.'),
            'company.required' => __('Which brand or company is this for?'),
            'email.required' => __('We need an email address to reply to.'),
            'email.email' => __('That email address does not look right.'),
            'phone.regex' => __('Use digits only, with an optional + at the start.'),
            'website.url' => __('That web address does not look right.'),
            'message.required' => __('A line or two about what you have in mind helps us reply properly.'),
            'message.min' => __('A little more detail, please - at least 10 characters.'),
        ];
    }

    /** Back to the form itself, not the top of the page, when something needs fixing. */
    protected function getRedirectUrl(): string
    {
        return route('sponsors').'#contact';
    }

    public function isBot(): bool
    {
        return filled($this->input(self::HONEYPOT));
    }
}
