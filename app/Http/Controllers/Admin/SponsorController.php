<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SponsorRequest;
use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Services\SponsorLogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The sponsors shown in the home page carousel and on /sponsors: their logo,
 * link, tier and order. Hidden sponsors stay here, ready to switch back on.
 */
class SponsorController extends Controller
{
    public function __construct(private readonly SponsorLogos $logos) {}

    public function index(): View
    {
        $sponsors = Sponsor::query()->showcase()->get();

        return view('admin.sponsors.index', [
            'sponsors' => $sponsors,
            'byTier' => collect(Sponsor::TIERS)->map(fn (string $label, string $tier) => $sponsors->where('tier', $tier)->values()),
            'counts' => [
                'live' => $sponsors->where('is_active', true)->count(),
                'hidden' => $sponsors->where('is_active', false)->count(),
                'requests' => SponsorInquiry::query()->unanswered()->count(),
            ],
        ]);
    }

    public function store(SponsorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Sponsor::create([
            'name' => $data['name'],
            'website' => $data['website'],
            'tier' => $data['tier'],
            'tagline' => $data['tagline'] ?? null,
            'is_active' => $data['is_active'],
            'logo_path' => $this->logos->store($request->file('logo')),
            // New sponsors join the end of their tier.
            'sort_order' => (int) Sponsor::query()->where('tier', $data['tier'])->max('sort_order') + 1,
        ]);

        return redirect()->route('admin.sponsors.index')->with('status', $data['name'].' added to the sponsors.');
    }

    public function edit(Sponsor $sponsor): View
    {
        return view('admin.sponsors.edit', ['sponsor' => $sponsor]);
    }

    public function update(SponsorRequest $request, Sponsor $sponsor): RedirectResponse
    {
        $data = $request->validated();
        $previousLogo = null;

        if ($request->hasFile('logo')) {
            $previousLogo = $sponsor->logo_path;
            $sponsor->logo_path = $this->logos->store($request->file('logo'));
        }

        if ($sponsor->tier !== $data['tier']) {
            $sponsor->sort_order = (int) Sponsor::query()->where('tier', $data['tier'])->max('sort_order') + 1;
        }

        $sponsor->fill([
            'name' => $data['name'],
            'website' => $data['website'],
            'tier' => $data['tier'],
            'tagline' => $data['tagline'] ?? null,
            'is_active' => $data['is_active'],
        ])->save();

        $this->logos->delete($previousLogo);

        return redirect()->route('admin.sponsors.index')->with('status', $sponsor->name.' updated.');
    }

    public function toggle(Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update(['is_active' => ! $sponsor->is_active]);

        return back()->with('status', $sponsor->is_active
            ? $sponsor->name.' is live on the site again.'
            : $sponsor->name.' is hidden from the site.');
    }

    /** One step up or down within its tier. */
    public function move(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $siblings = Sponsor::query()->where('tier', $sponsor->tier)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (Sponsor $item) => $item->is($sponsor));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $siblings->has($target)) {
            return back();
        }

        $order = $siblings->all();
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        // Renumber the whole tier so gaps and ties from older edits disappear.
        DB::transaction(function () use ($order) {
            foreach ($order as $position => $item) {
                $item->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $sponsor->delete();
        $this->logos->delete($sponsor->logo_path);

        return redirect()->route('admin.sponsors.index')->with('status', $sponsor->name.' removed from the sponsors.');
    }
}
