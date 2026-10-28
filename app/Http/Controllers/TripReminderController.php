<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripMember;
use App\Services\PaymentReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Remind" on the settle up panel: Sofor nudges a traveller about what they
 * owe, so the friend who is owed never has to ask. In-app and email are
 * free; a text comes out of the sender's SMS wallet.
 */
class TripReminderController extends Controller
{
    public function __construct(private readonly PaymentReminders $reminders) {}

    public function store(Request $request, Trip $trip, TripMember $member): RedirectResponse
    {
        $this->authorize('remind', [$trip, $member]);

        $result = $this->reminders->remind($trip, $member, $request->user(), $request->boolean('sms'));

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /** The leader nudges everybody who still owes, in one go. */
    public function storeAll(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        $sent = 0;
        $skipped = [];

        foreach ($trip->members()->with('user')->get() as $member) {
            if ($this->reminders->owes($trip, $member) === []) {
                continue;
            }

            $result = $this->reminders->remind($trip, $member, $request->user(), $request->boolean('sms'));
            $result['ok'] ? $sent++ : $skipped[] = $member->name;
        }

        if ($sent === 0) {
            return back()->with('error', $skipped === [] ? __('Nobody owes anything right now.') : __('Nobody could be reminded right now - they were all reminded recently or cannot be reached.'));
        }

        $done = $sent === 1 ? __('Reminded 1 traveller.') : __('Reminded :count travellers.', ['count' => $sent]);

        return back()->with('status', $skipped === []
            ? $done
            : $done.' '.__('Skipped :names (reminded recently or unreachable).', ['names' => implode(', ', $skipped)]));
    }

    /** Leader setting: also text the automatic reminders, from the leader's own SMS wallet. */
    public function settings(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('manageMembers', $trip);

        $trip->forceFill(['sms_reminders' => $request->boolean('sms_reminders')])->save();

        return back()->with('status', $trip->sms_reminders ? __('Automatic reminders will also go out by text, paid from your SMS wallet.') : __('Automatic reminders will go out in the app and by email only.'));
    }
}
