<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\TripMember;
use App\Services\ContributionService;
use App\Services\TripLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pay links: /p/<token>, a traveller's own page showing what they owe on one
 * trip and where to send it. No account needed - made for the friend, parent
 * or colleague who will never install anything. Whatever they report paying
 * waits for the receiver to confirm.
 */
class PayLinkController extends Controller
{
    /** At most this many unconfirmed reports from one link, so it cannot be used to spam. */
    private const MAX_PENDING = 5;

    public function show(string $token, TripLedger $ledger): View
    {
        $member = $this->member($token);
        $trip = $member->trip;
        $summary = $ledger->summarize($trip);
        $summary['members']->load('user.payoutMethods');

        return view('pay.show', [
            'member' => $member,
            'trip' => $trip,
            'balance' => $summary['balances'][$member->id] ?? null,
            'owes' => collect($summary['suggestions'])->filter(fn (array $s) => $s['from_id'] === $member->id)->values(),
            'shares' => $member->shares()->with('expense:id,title,spent_on')->get()->filter(fn ($share) => $share->expense !== null)->sortByDesc(fn ($share) => $share->expense->spent_on)->values(),
            'pending' => $trip->settlements()->whereNull('confirmed_at')->where('from_member_id', $member->id)->with('to')->get(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function paid(Request $request, string $token, ContributionService $contributions): RedirectResponse
    {
        $member = $this->member($token);
        $trip = $member->trip;

        $data = $request->validate([
            'to_member_id' => ['required', 'integer', Rule::in($trip->members()->whereKeyNot($member->id)->pluck('id')->all())],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Netted])],
            'reference' => ['nullable', 'string', 'max:80'],
        ], [
            'to_member_id.in' => __('Choose who you paid.'),
            'amount.min' => __('Enter the amount you paid.'),
        ]);

        if ($trip->settlements()->whereNull('confirmed_at')->where('from_member_id', $member->id)->count() >= self::MAX_PENDING) {
            return back()->with('error', __('You already have several payments waiting to be confirmed. Give them a little time.'));
        }

        $to = $trip->members()->findOrFail($data['to_member_id']);

        $contributions->record($trip, $member, $to, null, $data + ['settled_on' => now()->toDateString()]);

        return back()->with('status', __('Thanks! :to will confirm once they see the money.', ['to' => $to->name]));
    }

    private function member(string $token): TripMember
    {
        return TripMember::query()->where('pay_token', $token)->with(['trip', 'user'])->firstOrFail();
    }
}
