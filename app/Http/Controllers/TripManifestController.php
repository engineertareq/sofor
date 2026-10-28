<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Models\TravellerDetail;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\TripActivityLogger;
use App\Support\Phone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The checkpoint list: the ID details army and police checkpoints and hotels
 * ask for, collected from each traveller and printed by the leader.
 *
 * Each traveller sees and edits only their own details; the leader sees the
 * list (ID numbers masked on screen, in full on the printed PDF) and fills
 * in travellers who have no account. Everything is encrypted at rest and
 * deleted KEEP_DAYS after the trip ends.
 */
class TripManifestController extends Controller
{
    public const KEEP_DAYS = 14;

    public function __construct(private readonly TripActivityLogger $logger) {}

    public function show(Request $request, Trip $trip): View
    {
        $this->authorize('view', $trip);

        $user = $request->user();
        $canManage = $user->can('manageMembers', $trip);
        $me = $this->membership($request, $trip);

        abort_if($me === null && ! $canManage, 403);

        $travellers = $trip->members()->with('travellerDetail', 'user')->get()->filter(fn (TripMember $m) => $m->isTravelling())->values();
        $mine = $me?->travellerDetail;

        return view('trips.manifest', [
            'trip' => $trip,
            'travellers' => $travellers,
            'me' => $me,
            'mine' => $mine,
            // The form starts from this trip's details, or the traveller's saved copy.
            'prefill' => $mine?->data ?? ($user->travel_profile ?? []) + ['full_name' => $user->name, 'phone' => $user->phone],
            'remembered' => $user->travel_profile !== null,
            'canManage' => $canManage,
            'checkpoint' => $trip->checkpoint ?? [],
            'ready' => $travellers->filter(fn (TripMember $m) => $m->travellerDetail !== null)->count(),
            'purgeOn' => $trip->ended_at?->copy()->addDays(self::KEEP_DAYS),
        ]);
    }

    /** A traveller fills in (or updates) their own details. */
    public function saveMine(Request $request, Trip $trip): RedirectResponse
    {
        $me = $this->membership($request, $trip);
        abort_if($me === null || ! $trip->isActive(), 403);

        $data = $this->validated($request);

        TravellerDetail::updateOrCreate(['trip_member_id' => $me->id], ['data' => $data, 'submitted_by' => $request->user()->id]);

        // Keep a copy for next time only when asked to.
        $request->user()->forceFill(['travel_profile' => $request->boolean('remember') ? $data : null])->save();

        return redirect()->route('trips.manifest', $trip)->with('status', __('Your details are on the checkpoint list. Only you and the trip leader can see them.'));
    }

    public function destroyMine(Request $request, Trip $trip): RedirectResponse
    {
        $me = $this->membership($request, $trip);
        abort_if($me === null, 403);

        $me->travellerDetail()->delete();

        return redirect()->route('trips.manifest', $trip)->with('status', __('Your details were removed from this trip.'));
    }

    /** The leader fills in a traveller who has no Sofor account. */
    public function saveFor(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);
        abort_unless($member->trip_id === $trip->id && $member->user_id === null && $member->isTravelling() && $trip->isActive(), 404);

        TravellerDetail::updateOrCreate(['trip_member_id' => $member->id], ['data' => $this->validated($request), 'submitted_by' => $request->user()->id]);

        return redirect()->route('trips.manifest', $trip)->with('status', __(':member is on the checkpoint list.', ['member' => $member->name]));
    }

    /** Vehicle, driver and route: the part of the list that is about the trip. */
    public function saveTrip(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        $data = $request->validate([
            'vehicle' => ['nullable', 'string', 'max:60'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'driver_name' => ['nullable', 'string', 'max:80'],
            'driver_phone' => ['nullable', 'string', 'max:24'],
            'route' => ['nullable', 'string', 'max:160'],
            'stay' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        if (filled($data['driver_phone'] ?? null) && ! Phone::isValid(Phone::normalize($data['driver_phone']))) {
            return back()->withErrors(['driver_phone' => __('That mobile number does not look right.')])->withInput();
        }

        $data = array_map(fn ($value) => filled($value) ? trim((string) $value) : null, $data);
        $trip->forceFill(['checkpoint' => array_filter($data) ?: null])->save();

        return redirect()->route('trips.manifest', $trip)->with('status', __('Trip details for the checkpoint list saved.'));
    }

    /** Remind travellers with an account who have not filled in their details. */
    public function remind(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        $key = 'manifest-remind:'.$trip->id;

        if (Cache::has($key)) {
            return back()->with('error', __('Everyone was reminded recently. Try again later.'));
        }

        $missing = $trip->members()->with('user')->whereNotNull('user_id')->doesntHave('travellerDetail')->get()
            ->filter(fn (TripMember $m) => $m->isTravelling() && $m->user !== null && $m->user_id !== $request->user()->id);

        foreach ($missing as $member) {
            $this->logger->notifyUser(
                $member->user,
                $trip,
                Phrase::make('Add your details for the checkpoint list'),
                Phrase::make(':trip passes checkpoints that ask for every traveller\'s ID. It takes a minute, and only :user sees it.', ['trip' => $trip->title, 'user' => $request->user()->name]),
                'warning',
                'shield',
            );
        }

        Cache::put($key, true, now()->addHours(12));

        return back()->with('status', $missing->isEmpty() ? __('Everyone with an account has filled in their details.') : ($missing->count() === 1 ? __('Reminded 1 traveller.') : __('Reminded :count travellers.', ['count' => $missing->count()])));
    }

    /** The printable list. Leader only: it carries every full ID number. */
    public function pdf(Request $request, Trip $trip): Response
    {
        $this->authorize('manageMembers', $trip);

        $travellers = $trip->members()->with('travellerDetail')->get()->filter(fn (TripMember $m) => $m->isTravelling())->values();

        // The PDF font has no Bangla letters, so the list is always in English.
        app()->setLocale('en');

        $this->logger->log($trip, 'manifest_printed', Phrase::make(':user downloaded the checkpoint list', ['user' => $request->user()->name]), $request->user());

        $pdf = Pdf::loadView('trips.pdf.manifest', [
            'trip' => $trip,
            'travellers' => $travellers,
            'checkpoint' => $trip->checkpoint ?? [],
            'leader' => $trip->owner,
        ])
            ->setOption('isPhpEnabled', false)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('enable_font_subsetting', true)
            ->setPaper('a4', 'landscape')
            ->addInfo(['Title' => $trip->title.' - traveller list', 'Author' => 'Sofor', 'Creator' => 'Sofor']);

        $response = $pdf->download('sofor-'.(Str::slug($trip->title) ?: 'trip').'-traveller-list.pdf');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function membership(Request $request, Trip $trip): ?TripMember
    {
        return $trip->members()->where('user_id', $request->user()->id)->whereIn('status', ['active', 'invited'])->first();
    }

    /** @return array<string, string|null> */
    private function validated(Request $request): array
    {
        $request->merge([
            'phone' => filled($request->input('phone')) ? Phone::normalize((string) $request->input('phone')) : null,
            'emergency_phone' => filled($request->input('emergency_phone')) ? Phone::normalize((string) $request->input('emergency_phone')) : null,
            'id_number' => filled($request->input('id_number')) ? preg_replace('/\s+/', '', (string) $request->input('id_number')) : null,
        ]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:80'],
            'id_type' => ['required', Rule::in(array_keys(TravellerDetail::ID_TYPES))],
            'id_number' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{4,30}$/'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(array_keys(TravellerDetail::GENDERS))],
            'phone' => ['nullable', 'string', 'max:24'],
            'address' => ['nullable', 'string', 'max:160'],
            'blood_group' => ['nullable', Rule::in(TravellerDetail::BLOOD_GROUPS)],
            'emergency_name' => ['nullable', 'string', 'max:80'],
            'emergency_phone' => ['nullable', 'string', 'max:24'],
        ], [
            'id_number.regex' => __('Use the number exactly as printed on the card - letters and digits only.'),
            'date_of_birth.before' => __('That date of birth is in the future.'),
        ]);

        $errors = [];

        if ($data['id_type'] === 'nid' && ! preg_match('/^(\d{10}|\d{13}|\d{17})$/', $data['id_number'])) {
            $errors['id_number'] = __('A Bangladeshi NID number has 10, 13 or 17 digits.');
        }

        foreach (['phone', 'emergency_phone'] as $field) {
            if (filled($data[$field] ?? null) && ! Phone::isValid($data[$field])) {
                $errors[$field] = __('That mobile number does not look right.');
            }
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $data['date_of_birth'] = Carbon::parse($data['date_of_birth'])->toDateString();

        return collect(TravellerDetail::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => filled($data[$field] ?? null) ? trim((string) $data[$field]) : null])
            ->all();
    }
}
