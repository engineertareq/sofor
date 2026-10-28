<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SponsorInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Become a sponsor" requests sent from /sponsors. */
class SponsorInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));

        $inquiries = SponsorInquiry::query()
            ->when($status === 'open', fn ($query) => $query->where('status', '!=', SponsorInquiry::STATUS_CLOSED))
            ->when(array_key_exists($status, SponsorInquiry::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            // Unanswered first, newest first within each.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SponsorInquiry::STATUS_NEW])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = SponsorInquiry::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.sponsor-inquiries.index', [
            'inquiries' => $inquiries,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'open' => (int) ($counts[SponsorInquiry::STATUS_NEW] ?? 0) + (int) ($counts[SponsorInquiry::STATUS_CONTACTED] ?? 0),
                'new' => (int) ($counts[SponsorInquiry::STATUS_NEW] ?? 0),
                'contacted' => (int) ($counts[SponsorInquiry::STATUS_CONTACTED] ?? 0),
                'closed' => (int) ($counts[SponsorInquiry::STATUS_CLOSED] ?? 0),
                'all' => (int) $counts->sum(),
            ],
        ]);
    }

    public function show(SponsorInquiry $inquiry): View
    {
        return view('admin.sponsor-inquiries.show', ['inquiry' => $inquiry]);
    }

    public function update(Request $request, SponsorInquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SponsorInquiry::STATUSES))]]);

        $inquiry->update($data);

        return back()->with('status', $inquiry->company.' marked as '.strtolower($inquiry->statusLabel()).'.');
    }

    public function destroy(SponsorInquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()->route('admin.sponsor-inquiries.index')->with('status', 'Request from '.$inquiry->company.' deleted.');
    }
}
