<?php

namespace App\Http\Controllers;

use App\Support\Phrase;
use App\Exceptions\SmsWalletException;
use App\Models\Trip;
use App\Models\TripCheckin;
use App\Models\TripMember;
use App\Services\SmsWallet;
use App\Services\TripActivityLogger;
use App\Support\MailName;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Check-ins ("Reached Sajek") and each traveller's family link: a private page
 * the people at home can open to see where the group is. A check-in can also
 * be texted to the traveller's family, paid from their own SMS wallet.
 */
class TripSafetyController extends Controller
{
    public function __construct(
        private readonly TripActivityLogger $logger,
        private readonly SmsWallet $wallet,
    ) {}

    public function checkin(Request $request, Trip $trip): RedirectResponse
    {
        $member = $this->traveller($request, $trip);

        $data = $request->validate([
            'place' => ['required', 'string', 'min:2', 'max:80'],
            'note' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'sms' => ['nullable', 'boolean'],
        ]);

        $checkin = $trip->checkins()->create([
            'trip_member_id' => $member->id,
            'place' => trim($data['place']),
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        $this->logger->log($trip, 'checkin', Phrase::make(':member checked in: :place', ['member' => $member->name, 'place' => $checkin->place]), $request->user(), $member);
        $this->logger->notifyMembers($trip, Phrase::make(':member checked in', ['member' => $member->firstName()]), $checkin->note ? $checkin->place.' - '.$checkin->note : $checkin->place, 'info', 'map-pin', [$request->user()->id]);

        $status = __('Checked in: :place.', ['place' => $checkin->place]);

        if ($request->boolean('sms')) {
            if ($member->family_phone === null) {
                return back()->with('status', $status)->with('error', __('Add your family\'s number under "Family link" to text them check-ins.'));
            }

            try {
                $this->wallet->send($request->user(), $member->family_phone, $this->text($member, $checkin), 'Check-in text to family', $trip);
                $status .= ' '.__('Your family got a text.');
            } catch (SmsWalletException $e) {
                return back()->with('status', $status)->with('error', $e->getMessage());
            }
        }

        return back()->with('status', $status);
    }

    public function destroyCheckin(Request $request, Trip $trip, TripCheckin $checkin): RedirectResponse
    {
        abort_unless($checkin->trip_id === $trip->id, 404);

        $mine = $checkin->member?->user_id === $request->user()->id;
        abort_unless($mine || $request->user()->can('manageMembers', $trip), 403);

        $checkin->delete();

        return back()->with('status', __('Check-in removed.'));
    }

    /** Turn the family link on, or swap it for a new one (the old one stops working). */
    public function familyLink(Request $request, Trip $trip): RedirectResponse
    {
        $member = $this->traveller($request, $trip, activeOnly: false);

        $fresh = $member->family_token === null;
        $member->rotateFamilyToken();

        return back()->with('status', $fresh ? __('Your family link is ready. Share it with the people at home.') : __('New family link made. The old one no longer works.'));
    }

    public function disableFamilyLink(Request $request, Trip $trip): RedirectResponse
    {
        $member = $this->traveller($request, $trip, activeOnly: false);
        $member->forceFill(['family_token' => null])->saveQuietly();

        return back()->with('status', __('Family link turned off.'));
    }

    public function familyPhone(Request $request, Trip $trip): RedirectResponse
    {
        $member = $this->traveller($request, $trip, activeOnly: false);

        $phone = filled($request->input('family_phone')) ? Phone::normalize((string) $request->input('family_phone')) : null;

        if ($phone !== null && ! Phone::isValid($phone)) {
            return back()->withErrors(['family_phone' => __('That mobile number does not look right.')])->withInput();
        }

        $member->forceFill(['family_phone' => $phone])->saveQuietly();

        return back()->with('status', $phone ? __('Saved. Tick "Text my family" when you check in.') : __('Family number removed.'));
    }

    /** Fits one plain SMS for a short place name. */
    private function text(TripMember $member, TripCheckin $checkin): string
    {
        $place = MailName::text($checkin->place, 40);

        return 'Sofor: '.$member->firstName().' checked in - '.$place.', '.now()->format('g:ia').'. Follow the trip: '.$member->familyUrl();
    }

    private function traveller(Request $request, Trip $trip, bool $activeOnly = true): TripMember
    {
        $this->authorize('view', $trip);

        $member = $trip->members()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'invited'])
            ->first();

        abort_if($member === null || ($activeOnly && ! $trip->isActive()), 403);

        return $member;
    }
}
