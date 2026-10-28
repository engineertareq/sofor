<?php

namespace App\Http\Requests\Admin;

use App\Models\Sponsor;
use App\Services\SponsorLogos;
use App\Support\WebAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adding or editing a sponsor in the console. The logo is only required when adding. */
class SponsorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'website' => WebAddress::normalize($this->input('website')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'tier' => ['required', Rule::in(array_keys(Sponsor::TIERS))],
            'tagline' => ['nullable', 'string', 'max:140'],
            'is_active' => ['boolean'],
            'logo' => [
                $this->route('sponsor') ? 'nullable' : 'required',
                'image', 'mimes:png,jpg,jpeg,webp',
                'max:'.SponsorLogos::MAX_KILOBYTES,
                'dimensions:min_height=24',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the sponsor a name.',
            'website.url' => 'That web address does not look right.',
            'logo.required' => 'Choose the sponsor\'s logo.',
            'logo.uploaded' => 'That logo could not be uploaded. Keep it under 4 MB.',
            'logo.image' => 'Use a PNG, JPG or WebP image.',
            'logo.mimes' => 'Use a PNG, JPG or WebP image.',
            'logo.max' => 'Keep the logo under 4 MB.',
            'logo.dimensions' => 'That logo is too small. Use one at least 24 pixels tall.',
        ];
    }
}
