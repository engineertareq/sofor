<?php

namespace App\Policies;

use App\Enums\MemberStatus;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        if ($user->isAdmin() || $trip->user_id === $user->id) {
            return true;
        }

        return $trip->members()
            ->where('user_id', $user->id)
            ->whereIn('status', [MemberStatus::Active->value, MemberStatus::Invited->value])
            ->exists();
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->isAdmin() || $trip->user_id === $user->id;
    }

    /**
     * Only the leader deletes a trip - and not once travellers have paid
     * through bKash on it: those records are their proof of a real payment,
     * so the trip is ended instead. Admins can still remove it from the console.
     */
    public function delete(User $user, Trip $trip): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $trip->user_id === $user->id
            && $trip->settlements()->whereNotNull('gateway_trx_id')->doesntExist();
    }

    public function end(User $user, Trip $trip): bool
    {
        return ($user->isAdmin() || $trip->user_id === $user->id) && $trip->isActive();
    }

    public function manageMembers(User $user, Trip $trip): bool
    {
        return $user->isAdmin() || $trip->user_id === $user->id;
    }

    public function addExpense(User $user, Trip $trip): bool
    {
        if ($trip->isEnded()) {
            return false;
        }

        if ($user->isAdmin() || $trip->user_id === $user->id) {
            return true;
        }

        return $trip->members()
            ->where('user_id', $user->id)
            ->where('status', MemberStatus::Active->value)
            ->exists();
    }

    /** Members may log their own settlements, leaders may log anything. */
    public function recordSettlement(User $user, Trip $trip): bool
    {
        if ($user->isAdmin() || $trip->user_id === $user->id) {
            return true;
        }

        return $trip->members()
            ->where('user_id', $user->id)
            ->where('status', MemberStatus::Active->value)
            ->exists();
    }

    /**
     * Removing a contribution record: the leader, or whoever recorded it. A
     * payment bKash confirmed is proof that money really moved, so it is never
     * deleted from the app - otherwise the payer would appear to owe it again.
     */
    public function deleteSettlement(User $user, Trip $trip, Settlement $settlement): bool
    {
        if ($settlement->gateway !== null || $settlement->gateway_trx_id !== null) {
            return false;
        }

        // One part of a settle up across trips cannot go on its own: the rest
        // would no longer add up. A pending batch is rejected as a whole instead.
        if ($settlement->batch !== null) {
            return false;
        }

        if (! $this->recordSettlement($user, $trip)) {
            return false;
        }

        return $user->isAdmin() || $trip->user_id === $user->id || $settlement->recorded_by === $user->id;
    }

    /**
     * Confirming (or rejecting) a reported payment: the person who received
     * it, or the leader. Nobody else can know whether the money arrived.
     */
    public function confirmSettlement(User $user, Trip $trip, Settlement $settlement): bool
    {
        if (! $settlement->isPending()) {
            return false;
        }

        // A settle up across trips is between two friends: only the one who did
        // not write it down can accept it.
        if ($settlement->batch !== null) {
            return $settlement->recorded_by !== $user->id
                && in_array($user->id, [$settlement->from?->user_id, $settlement->to?->user_id], true);
        }

        return $user->isAdmin() || $trip->user_id === $user->id || $settlement->to?->user_id === $user->id;
    }

    /**
     * Reminding a traveller about money they owe: the leader, or somebody that
     * traveller owes. Nobody else gets to chase other people's debts.
     */
    public function remind(User $user, Trip $trip, TripMember $debtor): bool
    {
        if ($debtor->trip_id !== $trip->id || ! $this->recordSettlement($user, $trip)) {
            return false;
        }

        if ($user->isAdmin() || $trip->user_id === $user->id) {
            return true;
        }

        $myMemberId = $trip->members()->where('user_id', $user->id)->value('id');

        return collect(app(\App\Services\TripLedger::class)->summarize($trip)['suggestions'])
            ->contains(fn (array $suggestion) => $suggestion['from_id'] === $debtor->id && $suggestion['to_id'] === $myMemberId);
    }

    public function deleteExpense(User $user, Trip $trip): bool
    {
        return $this->addExpense($user, $trip);
    }

    /**
     * Editing or deleting one particular cost: the leader, or whoever logged
     * it. The same rule the trip feed uses to show the edit and delete buttons,
     * so another traveller cannot rewrite somebody's cost with a hand made request.
     */
    public function modifyExpense(User $user, Trip $trip, Expense $expense): bool
    {
        if (! $this->addExpense($user, $trip)) {
            return false;
        }

        return $user->isAdmin() || $trip->user_id === $user->id || $expense->created_by === $user->id;
    }
}
