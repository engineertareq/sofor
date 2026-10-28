<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\BudgetAlerts;
use App\Services\PortfolioService;
use App\Services\TripActivityLogger;
use App\Services\TravelSuggestions;
use App\Services\TripInvitationService;
use App\Services\TripEstimator;
use App\Services\TripLedger;
use App\Services\TripPot;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends Controller
{
    public function __construct(
        private readonly TripLedger $ledger,
        private readonly TripActivityLogger $logger,
        private readonly TripInvitationService $invitations,
    ) {}

    public function index(Request $request, PortfolioService $portfolio): View
    {
        $user = $request->user();

        $filter = $request->string('filter')->toString() ?: 'active';
        $role = $request->string('role')->toString() ?: 'all';
        $search = $request->string('q')->toString();

        $trips = Trip::query()
            ->visibleTo($user)
            ->with(['owner', 'members'])
            ->withCount('expenses')
            ->withSum('expenses', 'amount')
            ->search($search)
            ->when($filter === 'active', fn ($query) => $query->active())
            ->when($filter === 'ended', fn ($query) => $query->ended())
            ->when($role === 'owned', fn ($query) => $query->where('user_id', $user->id))
            ->when($role === 'joined', fn ($query) => $query->where('user_id', '!=', $user->id))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(9)
            ->withQueryString();

        $portfolio = $portfolio->forUser($user);

        return view('trips.index', [
            'trips' => $trips,
            'portfolio' => $portfolio,
            'filters' => ['filter' => $filter, 'role' => $role, 'q' => $search],
            'counts' => [
                'all' => Trip::visibleTo($user)->count(),
                'active' => Trip::visibleTo($user)->active()->count(),
                'ended' => Trip::visibleTo($user)->ended()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('trips.create', [
            'covers' => Trip::COVERS,
        ]);
    }

    /** Sofor is free: every account creates as many trips as it likes. */
    public function store(StoreTripRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // One transaction, so a trip never exists without its leader and invites.
        $trip = DB::transaction(function () use ($data, $user) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'destination' => $data['destination'] ?? null,
                'description' => $data['description'] ?? null,
                'cover' => $data['cover'],
                'emoji' => $data['icon'] ?? 'compass',
                'currency' => $data['currency'],
                ...Trip::budgetAttributes($data),
                'status' => 'active',
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'last_activity_at' => now(),
            ]);

            $this->invitations->ensureOwnerMember($trip, $user);

            $this->logger->log($trip, 'trip_created', Phrase::make(':user created the trip', ['user' => $user->name]), $user);

            foreach ($data['members'] ?? [] as $row) {
                $this->invitations->invite($trip, $user, $row);
            }

            return $trip;
        });

        $memberCount = $trip->members()->count();

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', trans_choice(':trip is live with :count traveller. Add the first cost to see the split.|:trip is live with :count travellers. Add the first cost to see the split.', $memberCount, ['trip' => $trip->title]));
    }

    public function show(Request $request, Trip $trip, TravelSuggestions $suggestions): View
    {
        $this->authorize('view', $trip);

        return view('trips.show', $this->viewData($request, $trip) + [
            // Places this trip mentions that are not on the viewer's travel map yet.
            'travelPrompt' => $suggestions->forTrip($request->user(), $trip),
        ]);
    }

    /**
     * Download the trip ledger as a real PDF.
     *
     * This renders a dedicated, print-shaped template through dompdf rather
     * than sending the traveller to the browser's print dialog. The template
     * is a financial document: what was spent, who paid, who owes whom, and
     * how the money breaks down by category.
     */
    public function summary(Request $request, Trip $trip): Response
    {
        $this->authorize('view', $trip);

        $slug = Str::slug($trip->title) ?: 'trip-'.$trip->id;

        // The PDF font has no Bangla letters, so the document is always in English.
        app()->setLocale('en');

        // dompdf caches the metrics of the taka font here; the folder may not exist yet on a fresh server.
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView('trips.pdf.summary', $this->viewData($request, $trip))
            // PHP inside the template stays off: the page footer is drawn below instead.
            ->setOption('isPhpEnabled', false)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans')
            // Subsetting embeds only the glyphs actually used, which takes the
            // file from about 1.4 MB down to a few tens of KB.
            ->setOption('enable_font_subsetting', true)
            ->setPaper('a4', 'portrait')
            ->addInfo([
                'Title' => $trip->title.' - Sofor trip summary',
                'Author' => 'Sofor',
                'Subject' => __('Trip cost summary for :trip', ['trip' => $trip->title]),
                'Creator' => 'Sofor',
            ]);

        // "Page 1 of 2" on every page, written onto the rendered pages.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans Mono');
        $dompdf->getCanvas()->page_text(32, 806, 'Sofor - trip summary', $font, 6.6, [0.55, 0.59, 0.55]);
        $dompdf->getCanvas()->page_text(500, 806, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 6.6, [0.55, 0.59, 0.55]);

        return $pdf->download('sofor-'.$slug.'-summary.pdf');
    }

    public function edit(Request $request, Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('trips.edit', [
            'trip' => $trip,
            'covers' => Trip::COVERS,
        ]);
    }

    public function update(UpdateTripRequest $request, Trip $trip, BudgetAlerts $budgetAlerts): RedirectResponse
    {
        $data = $request->validated();

        $trip->update([
            'title' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'description' => $data['description'] ?? null,
            'cover' => $data['cover'],
            'emoji' => $data['icon'] ?? $trip->icon,
            'currency' => $data['currency'],
            ...Trip::budgetAttributes($data),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);

        $this->logger->log($trip, 'trip_updated', Phrase::make(':user updated the trip details', ['user' => $request->user()->name]), $request->user());

        $budgetAlerts->check($trip);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip details updated.'));
    }

    public function end(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('end', $trip);

        $trip->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
        ])->save();

        $summary = $this->ledger->summarize($trip, $request->user());

        $this->logger->log(
            $trip,
            'trip_ended',
            Phrase::make(':user ended the trip at :amount', ['user' => $request->user()->name, 'amount' => Phrase::money($summary['stats']['spent'], $trip->currency)]),
            $request->user(),
            null,
            ['spent' => $summary['stats']['spent']],
        );

        $this->logger->notifyMembers(
            $trip,
            Phrase::make(':trip has ended', ['trip' => $trip->title]),
            Phrase::make('Total spend :amount. Costs are now locked - settle the remaining dues.', ['amount' => Phrase::money($summary['stats']['spent'], $trip->currency)]),
            'warning',
            'lock',
            [$request->user()->id],
        );

        return redirect()->route('trips.show', $trip)->with('status', __('Trip ended. Costs are locked and the final settlement has been calculated.'));
    }

    public function destroy(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $title = $trip->title;
        $trip->delete();

        return redirect()->route('trips.index')->with('status', __(':title was deleted.', ['title' => $title]));
    }

    public function leave(Request $request, Trip $trip): RedirectResponse
    {
        $user = $request->user();
        $member = $trip->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            return redirect()->route('trips.index')->with('error', __('You are not part of this trip.'));
        }

        if ($member->isOwner()) {
            return redirect()->route('trips.show', $trip)->with('error', __('The trip leader cannot leave. End or delete the trip instead.'));
        }

        $hasActivity = $member->shares()->exists() || $trip->expenses()->where('trip_member_id', $member->id)->exists();

        if ($hasActivity) {
            // Keep the history intact, just stop including them going forward.
            $member->update([
                'status' => MemberStatus::Removed->value,
                'include_in_split' => false,
            ]);
        } else {
            $member->delete();
        }

        $this->logger->log($trip, 'member_left', Phrase::make(':user left the trip', ['user' => $user->name]), $user, null);
        $this->logger->notifyMembers($trip, Phrase::make(':user left :trip', ['user' => $user->name, 'trip' => $trip->title]), Phrase::make('Balances were recalculated.'), 'warning', 'users', [$user->id]);

        return redirect()->route('trips.index')->with('status', __('You left :trip.', ['trip' => $trip->title]));
    }

    public function regenerateInvite(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        $trip->forceFill(['invite_code' => Trip::freshInviteCode()])->save();

        $this->logger->log($trip, 'invite_rotated', Phrase::make(':user generated a new invite link', ['user' => $request->user()->name]), $request->user());

        return back()->with('status', __('New invite link generated. The old link no longer works.'));
    }

    /**
     * Rendered HTML fragments used by the realtime stream to patch the page
     * without a full reload.
     */
    public function live(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $data = $this->viewData($request, $trip);

        $sinceActivity = (int) $request->integer('since_activity');

        $events = $data['activities']
            ->filter(fn ($activity) => $activity->id > $sinceActivity)
            ->take(4)
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'description' => $activity->text(),
                'time' => $activity->created_at?->diffForHumans(),
            ])
            ->values();

        return response()->json([
            'stamp' => $trip->activityStamp(),
            'latest_activity_id' => $data['activities']->max('id') ?? 0,
            'stats' => [
                'spent' => $data['ledger']['stats']['spent'],
                'per_person' => $data['ledger']['stats']['per_person'],
                'travellers' => $data['ledger']['stats']['travellers'],
                'settled' => $data['ledger']['progress']['settled'],
                'status' => $trip->status->value,
            ],
            'events' => $events,
            'html' => [
                'summary' => view('trips.partials.summary', $data)->render(),
                'members' => view('trips.partials.members', $data)->render(),
                'feed' => view('trips.partials.feed', $data)->render(),
                'settle' => view('trips.partials.settle', $data)->render(),
                'plan' => view('trips.partials.plan', $data)->render(),
                'breakdown' => view('trips.partials.breakdown', $data)->render(),
                'activity' => view('trips.partials.activity', $data)->render(),
            ],
        ]);
    }

    /**
     * Server-sent events: the client is notified the moment the trip changes.
     */
    public function stream(Request $request, Trip $trip): StreamedResponse
    {
        $this->authorize('view', $trip);

        $tripId = $trip->id;
        $lastStamp = $trip->activityStamp();

        $this->touchMemberSeen($request->user()->id, $trip);

        // PHP's built in server (php artisan serve) handles one request at a time,
        // so holding a stream open would freeze the whole site. In that case the
        // client is told to fall back to polling. Apache/nginx/FPM stream normally.
        $singleThreaded = php_sapi_name() === 'cli-server'
            && ! filter_var(env('SOFOR_SSE_STREAMING', false), FILTER_VALIDATE_BOOL);

        if ($singleThreaded) {
            return response()->stream(function () {
                $this->prepareStream();

                echo "retry: 15000\n\n";
                echo "event: poll\ndata: {\"reason\":\"single-threaded-server\"}\n\n";
                $this->flushStream();
            }, 200, [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () use ($tripId, $lastStamp) {
            $this->prepareStream();

            $deadline = microtime(true) + 45;
            $pings = 0;

            echo "retry: 4000\n\n";
            echo 'event: ready'."\n".'data: '.json_encode(['stamp' => $lastStamp])."\n\n";
            $this->flushStream();

            while (! connection_aborted() && microtime(true) < $deadline) {
                usleep(2_500_000);
                $pings++;

                $trip = Trip::find($tripId);

                if ($trip === null) {
                    echo "event: closed\ndata: {\"reason\":\"deleted\"}\n\n";
                    $this->flushStream();

                    break;
                }

                $stamp = $trip->activityStamp();

                if ($stamp !== $lastStamp) {
                    $lastStamp = $stamp;
                    echo 'event: change'."\n".'data: '.json_encode(['stamp' => $stamp])."\n\n";
                } else {
                    echo ': keepalive '.$pings."\n\n";
                }

                $this->flushStream();
            }

            echo "event: reconnect\ndata: {}\n\n";
            $this->flushStream();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function touchMemberSeen(int $userId, Trip $trip): void
    {
        $trip->members()->where('user_id', $userId)->update(['last_seen_at' => now()]);
    }

    private function prepareStream(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('max_execution_time', '0');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @ob_implicit_flush(true);
    }

    private function flushStream(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        @flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Request $request, Trip $trip): array
    {
        $ledger = $this->ledger->summarize($trip, $request->user());

        // Where each traveller wants to be paid, for the settle up panel.
        $ledger['members']->load('user.payoutMethods');

        $expenses = $trip->expenses()
            ->with(['payer', 'shares.member', 'author'])
            ->get();

        $settlements = $trip->settlements()->with(['from.user', 'to.user'])->take(30)->get();

        $activities = $trip->activities()->with(['user', 'member'])->take(40)->get();

        $currentMember = $trip->members()->where('user_id', $request->user()->id)->first();

        // No budget yet: what trips to the same place usually cost, as a nudge to set one.
        $estimate = ! $trip->hasBudget() && $trip->isActive()
            ? app(TripEstimator::class)->estimate(
                $trip->destination,
                $trip->currency,
                $trip->starts_on && $trip->ends_on ? (int) $trip->starts_on->diffInDays($trip->ends_on) + 1 : null,
                $trip->id,
            )
            : null;

        return [
            'trip' => $trip,
            'ledger' => $ledger,
            'expenses' => $expenses,
            'settlements' => $settlements,
            'activities' => $activities,
            'currentMember' => $currentMember,
            'canAddExpense' => $request->user()->can('addExpense', $trip),
            'canManage' => $request->user()->can('manageMembers', $trip),
            'canUpdate' => $request->user()->can('update', $trip),
            'estimate' => $estimate,
            'pots' => app(TripPot::class)->summaries($trip, $currentMember),
            'polls' => $trip->polls()->with(['author', 'options.votes.member.user'])->take(12)->get(),
            'checkins' => $trip->checkins()->with('member')->take(6)->get(),
            'canRecord' => $request->user()->can('recordSettlement', $trip),
            'categories' => \App\Enums\ExpenseCategory::options(),
            'paymentMethods' => \App\Enums\PaymentMethod::options(),
            'inviteUrl' => $trip->inviteUrl(),
        ];
    }
}
