<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Enums\PaymentMethod;
use App\Models\Settlement;
use App\Models\User;
use App\Services\ContributionService;
use App\Services\CrossTripBalances;
use App\Services\TripActivityLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /balances: where you stand with each friend over every trip you shared,
 * and settling it all with one payment.
 *
 * "Settle up" writes one payment into every trip involved, tied together in
 * a batch. When you are the one who owes, your friend confirms the batch
 * (or says it never arrived, which takes it all back off). When your friend
 * paid you, you are the one giving up the claim, so it counts at once.
 */
class BalancesController extends Controller
{
    public function __construct(
        private readonly CrossTripBalances $balances,
        private readonly ContributionService $contributions,
        private readonly TripActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $rows = $this->balances->forUser($user);

        // Settle ups waiting for the other side, one entry per batch.
        $pending = Settlement::query()
            ->whereNotNull('batch')
            ->whereNull('confirmed_at')
            ->where(fn ($query) => $query
                ->whereHas('from', fn ($m) => $m->where('user_id', $user->id))
                ->orWhereHas('to', fn ($m) => $m->where('user_id', $user->id)))
            ->with(['trip', 'from.user', 'to.user'])
            ->get()
            ->groupBy('batch');

        return view('balances.index', [
            'rows' => $rows,
            'owe' => $rows->filter(fn ($row) => $row['net'] < -0.009)->values(),
            'owed' => $rows->filter(fn ($row) => $row['net'] > 0.009)->values(),
            'even' => $rows->filter(fn ($row) => abs($row['net']) <= 0.009)->values(),
            'pending' => $pending,
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function settle(Request $request, User $friend): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Netted])],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        abort_if($friend->id === $user->id, 404);

        $row = $this->balances->between($user, $friend, strtoupper($data['currency']));

        if ($row === null || $row['items'] === []) {
            return back()->with('error', __('You and :friend are already square.', ['friend' => $friend->name]));
        }

        $iPay = $row['net'] < 0;
        $net = abs($row['net']);
        $batch = Str::random(16);
        $currency = $row['currency'];
        // Written for each reader: the note and the notification may be read in another language.
        $cash = Phrase::money($net, $currency);
        $method = PaymentMethod::from($data['method']);
        $trips = collect($row['items'])->pluck('trip')->unique('id');

        DB::transaction(function () use ($row, $user, $iPay, $batch, $method, $data, $cash) {
            // The real money rides on the first payment in its direction; the rest is netted.
            $cashCarried = false;

            foreach ($row['items'] as $item) {
                $carriesCash = ! $cashCarried && $item['you_owe'] === $iPay && abs($row['net']) > 0.009;
                $cashCarried = $cashCarried || $carriesCash;

                $this->contributions->record($item['trip'], $item['from'], $item['to'], $user, [
                    'amount' => $item['amount'],
                    'method' => $carriesCash ? $method->value : PaymentMethod::Netted->value,
                    'reference' => $carriesCash ? ($data['reference'] ?? null) : null,
                    'note' => ($iPay
                        ? __('Settled across trips - :cash paid by :method, the rest netted.', ['cash' => $cash, 'method' => $method->label()])
                        : __('Settled across trips - :cash received by :method, the rest netted.', ['cash' => $cash, 'method' => $method->label()])),
                    'settled_on' => now()->toDateString(),
                    'batch' => $batch,
                    // Paying out needs the friend to confirm; being paid gives up your own claim.
                    'confirmed' => ! $iPay,
                    'quiet' => true,
                ]);
            }
        });

        $tripCount = $trips->count();

        if ($iPay) {
            $this->logger->notifyUser(
                $friend,
                $trips->first(),
                Phrase::make('Did :user pay you :cash?', ['user' => $user->name, 'cash' => $cash]),
                filled($data['reference'] ?? null)
                    ? Phrase::make(':user settled everything between you across :count trips: :cash by :method (transaction :reference). Confirm it once you see the money.', [
                        'user' => $user->name, 'count' => $tripCount, 'cash' => $cash, 'method' => $method->label(), 'reference' => $data['reference'],
                    ])
                    : Phrase::make(':user settled everything between you across :count trips: :cash by :method. Confirm it once you see the money.', [
                        'user' => $user->name, 'count' => $tripCount, 'cash' => $cash, 'method' => $method->label(),
                    ]),
                'warning',
                'wallet',
            );

            return back()->with('status', __('Recorded across :count trips. :friend is asked to confirm the :cash.', ['count' => $tripCount, 'friend' => $friend->name, 'cash' => $cash]));
        }

        $this->logger->notifyUser(
            $friend,
            $trips->first(),
            Phrase::make('You are square with :user', ['user' => $user->name]),
            Phrase::make(':user marked your :cash as received. Everything between you across :count trips is settled.', ['user' => $user->name, 'cash' => $cash, 'count' => $tripCount]),
            'success',
            'check-circle',
        );

        return back()->with('status', __('Marked :cash from :friend as received. You are square across :count trips.', ['cash' => $cash, 'friend' => $friend->name, 'count' => $tripCount]));
    }
}
