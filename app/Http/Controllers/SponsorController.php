<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Http\Requests\StoreSponsorInquiryRequest;
use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Models\Trip;
use App\Models\User;
use App\Models\VisitedPlace;
use App\Services\SponsorLogos;
use App\Services\SponsorShowcase;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public sponsors page: who backs Sofor, what sponsoring gets you, and a
 * form to get in touch. Requests land in Admin -> Sponsor requests.
 */
class SponsorController extends Controller
{
    use StreamsMedia;

    /** Audience numbers are only shown once they say something. */
    private const AUDIENCE_THRESHOLD = 50;

    public function index(SponsorShowcase $showcase): View
    {
        $sponsors = $showcase->live();
        $travellers = User::query()->where('role', '!=', 'admin')->count();

        $requestedTier = (string) request()->query('tier', '');

        return view('sponsors.index', [
            'sponsors' => $sponsors,
            'byTier' => collect(Sponsor::TIERS)
                ->map(fn (string $label, string $tier) => $sponsors->where('tier', $tier)->values())
                ->filter(fn ($group) => $group->isNotEmpty()),
            'audience' => $travellers >= self::AUDIENCE_THRESHOLD ? [
                'travellers' => $travellers,
                'trips' => Trip::count(),
                'places' => VisitedPlace::count(),
            ] : null,
            'selectedTier' => array_key_exists($requestedTier, Sponsor::TIERS) ? $requestedTier : '',
        ]);
    }

    public function storeInquiry(StoreSponsorInquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Bots get the same thank you, so there is nothing to learn from trying again.
        if (! $request->isBot()) {
            SponsorInquiry::create($data + [
                'status' => SponsorInquiry::STATUS_NEW,
                'ip_address' => $request->ip(),
            ]);
        }

        return redirect()->to(route('sponsors').'#contact')
            ->with('sponsor_inquiry', ['name' => $data['name'], 'email' => $data['email']]);
    }

    /**
     * A logo by its file name. Visitors get the logos of live sponsors; admins
     * also see hidden ones, so the console can preview them.
     *
     * Pages link logos on Cloudflare R2 straight to the bucket's public URL,
     * so this only streams from the local disk; older links to a logo that
     * has since moved to R2 are sent on to it.
     */
    public function logo(string $file): StreamedResponse|RedirectResponse
    {
        $path = SponsorLogos::DIRECTORY.'/'.$file;
        $sponsor = Sponsor::query()->where('logo_path', $path)->first();

        abort_if($sponsor === null, 404);
        abort_unless($sponsor->is_active || auth()->user()?->isAdmin(), 404);

        $url = SponsorLogos::url($path);

        if ($url !== route('sponsors.logo', $file)) {
            return redirect()->away($url, 301);
        }

        return $this->streamMedia(SponsorLogos::disk(), $path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => $sponsor->is_active ? SponsorLogos::CACHE_CONTROL : 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
