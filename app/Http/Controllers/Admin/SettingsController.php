<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Platform settings: the logo and favicon shown across the whole site. */
class SettingsController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function edit(): View
    {
        return view('admin.settings', ['branding' => $this->branding]);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => [
                'required', 'image', 'mimes:png,jpg,jpeg,webp',
                'max:'.BrandingService::LOGO_MAX_KILOBYTES,
                'dimensions:min_height=32',
            ],
        ], $this->messages('logo', 'Keep the logo under 4 MB.', 'That logo is too small. Use one at least 32 pixels tall.'));

        $this->branding->replaceLogo($request->file('logo'));

        return back()->with('status', 'Logo updated across Sofor.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->branding->removeLogo();

        return back()->with('status', 'Logo reset to the built in Sofor logo.');
    }

    public function updateFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => [
                'required', 'image', 'mimes:png,jpg,jpeg,webp',
                'max:'.BrandingService::FAVICON_MAX_KILOBYTES,
                'dimensions:min_width=48,min_height=48',
            ],
        ], $this->messages('favicon', 'Keep the favicon under 2 MB.', 'That favicon is too small. Use one at least 48 pixels across.'));

        $this->branding->replaceFavicon($request->file('favicon'));

        return back()->with('status', 'Favicon updated across Sofor.');
    }

    public function destroyFavicon(): RedirectResponse
    {
        $this->branding->removeFavicon();

        return back()->with('status', 'Favicon reset to the built in Sofor icon.');
    }

    /** @return array<string, string> */
    private function messages(string $field, string $tooBig, string $tooSmall): array
    {
        return [
            "$field.required" => 'Choose an image to upload.',
            "$field.uploaded" => 'That image could not be uploaded. '.$tooBig,
            "$field.image" => 'Use a PNG, JPG or WebP image.',
            "$field.mimes" => 'Use a PNG, JPG or WebP image.',
            "$field.max" => $tooBig,
            "$field.dimensions" => $tooSmall,
        ];
    }
}
