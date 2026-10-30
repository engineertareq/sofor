<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\TourStatus;
use App\Http\Requests\SaveTourRequest;
use App\Models\Tour;
use App\Models\TourPayment;
use App\Models\Trip;
use App\Services\SmsWallet;
use App\Services\TourAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Organiser mode: tours an organiser sells seats on. This is their side - the
 * tours, the dashboard, the settings. The public page and each participant's
 * booking page are PublicTourController and TourTicketController.
 */
class TourController extends Controller
{
    /** Which way a tour can go from where it is. */
    private const NEXT = [
        'draft' => ['open', 'cancelled'],
        'open' => ['closed', 'completed', 'cancelled'],
        'closed' => ['open', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $tours = $request->user()->tours()
            ->withSum(['registrations as seats_booked' => fn ($query) => $query->where('status', 'booked')], 'seats')
            ->withSum(['payments as paid_in' => fn ($query) => $query->where('status', TourPayment::STATUS_CONFIRMED)->where('type', TourPayment::TYPE_PAYMENT)], 'amount')
            ->withSum(['payments as paid_out' => fn ($query) => $query->where('status', TourPayment::STATUS_CONFIRMED)->where('type', TourPayment::TYPE_REFUND)], 'amount')
            ->withCount(['payments as pending_count' => fn ($query) => $query->where('status', TourPayment::STATUS_PENDING)])
            ->get();

        return view('tours.index', ['tours' => $tours]);
    }

    public function create(): View
    {
        return view('tours.create', ['tour' => new Tour(['cover' => 'aurora', 'show_account' => true])]);
    }

    public function store(SaveTourRequest $request): RedirectResponse
    {
        $tour = DB::transaction(function () use ($request) {
            $tour = new Tour($request->safe()->except('packages'));
            $tour->organiser()->associate($request->user());
            $tour->status = $request->boolean('publish') ? TourStatus::Open : TourStatus::Draft;
            $tour->save();

            $this->syncPackages($tour, $request->validated('packages'));

            return $tour;
        });

        return redirect()->route('tours.show', $tour)->with('status', $tour->status === TourStatus::Open
            ? __('Your tour is live. Share its link to take bookings.')
            : __('Saved as a draft. Open registration when you are ready.'));
    }

    public function show(Request $request, Tour $tour, TourAccount $account, SmsWallet $wallet): View
    {
        $this->authorize('manage', $tour);

        $tour->load(['registrations' => fn ($query) => $query->latest(), 'registrations.payments', 'registrations.package', 'expenses' => fn ($query) => $query->latest('spent_on')->latest('id')]);

        return view('tours.show', [
            'tour' => $tour,
            'account' => $account->summarize($tour),
            'payments' => $tour->registrations->flatMap(fn ($registration) => $registration->payments->each(fn ($payment) => $payment->setRelation('registration', $registration)))
                ->sortByDesc(fn (TourPayment $payment) => $payment->paid_on->timestamp * 1000000 + $payment->id)
                ->values(),
            'methods' => PaymentMethod::options(),
            'categories' => collect(ExpenseCategory::cases())->mapWithKeys(fn (ExpenseCategory $category) => [$category->value => $category->label()])->all(),
            'next' => self::NEXT[$tour->status->value],
            'smsPrice' => $wallet->price(),
            'smsBalance' => (float) $request->user()->sms_balance,
        ]);
    }

    public function edit(Tour $tour): View
    {
        $this->authorize('manage', $tour);

        return view('tours.edit', ['tour' => $tour->load('packages')]);
    }

    public function update(SaveTourRequest $request, Tour $tour): RedirectResponse
    {
        DB::transaction(function () use ($request, $tour) {
            $tour->update($request->safe()->except('packages'));
            $this->syncPackages($tour, $request->validated('packages'));
        });

        return redirect()->route('tours.show', $tour)->with('status', __('Tour saved.'));
    }

    public function status(Request $request, Tour $tour): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $to = $request->validate([
            'status' => ['required', Rule::in(self::NEXT[$tour->status->value])],
        ], ['status.in' => __('That change is not possible from here.')])['status'];

        $tour->status = TourStatus::from($to);
        $tour->save();

        return back()->with('status', match ($tour->status) {
            TourStatus::Open => __('Registration is open. Share the link to take bookings.'),
            TourStatus::Closed => __('Registration is closed. Bookings and payments carry on as usual.'),
            TourStatus::Completed => __('Marked as completed. Well done!'),
            TourStatus::Cancelled => __('The tour is cancelled. Record refunds as you send them.'),
            default => __('Tour saved.'),
        });
    }

    /** Whether everyone who booked sees the account: what came in and what was spent. */
    public function sharing(Request $request, Tour $tour): RedirectResponse
    {
        $this->authorize('manage', $tour);

        $tour->show_account = $request->boolean('show_account');
        $tour->save();

        return back()->with('status', $tour->show_account
            ? __('Everyone who booked can now see the account.')
            : __('The account is private again.'));
    }

    public function destroy(Tour $tour): RedirectResponse
    {
        $this->authorize('manage', $tour);

        if ($tour->registrations()->exists()) {
            return back()->with('error', __('People have booked this tour, so it cannot be deleted. Cancel it instead.'));
        }

        $tour->delete();

        return redirect()->route('tours.index')->with('status', __('Tour deleted.'));
    }

    /**
     * Packages are matched by id: one kept is updated, a new one created, and one
     * left out deleted (SaveTourRequest makes sure nobody booked it).
     *
     * @param  list<array{id?: ?int, name: string, description?: ?string, price: float|string, seats: int, advance?: float|string|null}>  $rows
     */
    private function syncPackages(Tour $tour, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $order => $row) {
            $package = filled($row['id'] ?? null) ? $tour->packages()->findOrFail($row['id']) : $tour->packages()->make();
            $package->fill([
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'price' => $row['price'],
                'seats' => $row['seats'],
                'advance' => filled($row['advance'] ?? null) ? $row['advance'] : null,
                'sort_order' => $order,
            ])->save();

            $kept[] = $package->id;
        }

        $tour->packages()->whereNotIn('id', $kept)->delete();
    }

    /** The cover swatches for the form, with their names. */
    public static function covers(): array
    {
        return collect(Trip::COVERS)->mapWithKeys(fn ($style, $key) => [$key => ['style' => $style, 'label' => Trip::coverLabel($key)]])->all();
    }
}
