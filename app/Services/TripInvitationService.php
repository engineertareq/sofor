<?php

namespace App\Services;

use App\Support\Phrase;
use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Invites work for people who already have a Sofor account and for people who
 * do not: unclaimed invites sit on the trip until that email or phone signs up,
 * then they are attached automatically at login.
 */
class TripInvitationService
{
    public function __construct(private readonly TripActivityLogger $logger) {}

    public function ensureOwnerMember(Trip $trip, User $owner): TripMember
    {
        $member = $trip->members()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'role' => 'owner',
                'status' => MemberStatus::Active->value,
                'include_in_split' => true,
                'share_units' => 1,
                'joined_at' => now(),
            ],
        );

        // Whoever starts the trip is going.
        if ($member->wasRecentlyCreated) {
            $member->forceFill(['rsvp' => 'going', 'rsvp_at' => now()])->save();
        }

        return $member;
    }

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, share_units?: int, include_in_split?: bool}  $data
     * @return array{member: TripMember, created: bool, registered: bool, self: bool, message: string}
     */
    public function invite(Trip $trip, User $inviter, array $data): array
    {
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;
        $phone = filled($data['phone'] ?? null) ? Phone::normalize((string) $data['phone']) : null;
        $name = filled($data['name'] ?? null) ? trim((string) $data['name']) : null;

        // Only match against a real account when we actually have contact
        // details - matching on nothing used to grab an arbitrary user.
        $matchedUser = User::findByContact($email, $phone);

        $existing = $this->findExistingMember($trip, $email, $phone, $name, $matchedUser);

        if ($existing !== null) {
            return $this->refreshExisting($trip, $existing, $data, $email, $phone, $matchedUser);
        }

        try {
            $member = $trip->members()->create([
                'user_id' => $matchedUser?->id,
                'invited_by' => $inviter->id,
                'name' => $name ?? $matchedUser?->name ?? 'Traveller',
                'email' => $email,
                'phone' => $phone,
                'role' => 'member',
                'status' => $matchedUser !== null
                    ? MemberStatus::Active->value
                    : MemberStatus::Invited->value,
                'include_in_split' => $data['include_in_split'] ?? true,
                'share_units' => max(1, (int) ($data['share_units'] ?? 1)),
                'invite_token' => Str::random(32),
                'invited_at' => now(),
                'joined_at' => $matchedUser !== null ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two invites for the same account raced each other: fold the second
            // one into the row that already exists instead of failing.
            $existing = $trip->members()
                ->when(
                    $matchedUser !== null,
                    fn ($query) => $query->where('user_id', $matchedUser->id),
                    fn ($query) => $query->where('email', $email)->orWhere('phone', $phone),
                )
                ->first();

            if ($existing === null) {
                throw new UniqueConstraintViolationException;
            }

            return $this->refreshExisting($trip, $existing, $data, $email, $phone, $matchedUser);
        }

        $this->logger->log(
            $trip,
            'member_invited',
            Phrase::make(':inviter added :member to the trip', ['inviter' => $inviter->name, 'member' => $member->name]),
            $inviter,
            $member,
            ['member_name' => $member->name],
        );

        if ($matchedUser !== null) {
            $this->logger->notifyUser(
                $matchedUser,
                $trip,
                Phrase::make('You were added to :trip', ['trip' => $trip->title]),
                Phrase::make(':inviter added you as a traveller. Tap to see the split.', ['inviter' => $inviter->name]),
                'info',
                'users',
            );
        }

        return [
            'member' => $member,
            'created' => true,
            'registered' => $matchedUser !== null,
            'self' => false,
            'message' => $matchedUser !== null ? __(':member was added and can already see the split.', ['member' => $member->name]) : __('Invite saved for :member. They join automatically once they sign up with :contact_label.', ['member' => $member->name, 'contact_label' => $member->contactLabel()]),
        ];
    }

    /**
     * Finds the row that already represents this person on the trip - by their
     * account, by their contact details, or by name when nothing else is known.
     */
    private function findExistingMember(Trip $trip, ?string $email, ?string $phone, ?string $name, ?User $matchedUser): ?TripMember
    {
        if ($matchedUser !== null) {
            $byAccount = $trip->members()->where('user_id', $matchedUser->id)->first();

            if ($byAccount !== null) {
                return $byAccount;
            }
        }

        if ($email !== null || $phone !== null) {
            return $trip->members()
                ->where(function ($query) use ($email, $phone) {
                    if ($email !== null) {
                        $query->orWhere('email', $email);
                    }

                    if ($phone !== null) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->first();
        }

        // Name only: fold into an existing traveller who has no account and no
        // contact details either, so a double submit cannot create a phantom.
        if ($name !== null) {
            return $trip->members()
                ->whereNull('user_id')
                ->whereNull('email')
                ->whereNull('phone')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{member: TripMember, created: bool, registered: bool, self: bool, message: string}
     */
    private function refreshExisting(Trip $trip, TripMember $member, array $data, ?string $email, ?string $phone, ?User $matchedUser): array
    {
        if ($member->isOwner()) {
            return [
                'member' => $member,
                'created' => false,
                'registered' => true,
                'self' => true,
                'message' => __(':member already leads this trip, so nothing changed.', ['member' => $member->name]),
            ];
        }

        $updates = [
            'status' => $member->status === MemberStatus::Removed ? MemberStatus::Active->value : $member->status->value,
            'include_in_split' => array_key_exists('include_in_split', $data) && $data['include_in_split'] !== null
                ? (bool) $data['include_in_split']
                : $member->include_in_split,
        ];

        if (filled($data['share_units'] ?? null)) {
            $updates['share_units'] = max(1, (int) $data['share_units']);
        }

        if (filled($data['name'] ?? null)) {
            $updates['name'] = trim((string) $data['name']);
        }

        // Fill in missing contact details rather than overwriting the details
        // this traveller is already identified by.
        if ($email !== null && $member->email === null) {
            $updates['email'] = $email;
        }

        if ($phone !== null && $member->phone === null) {
            $updates['phone'] = $phone;
        }

        if ($matchedUser !== null && $member->user_id === null) {
            $updates['user_id'] = $matchedUser->id;
            $updates['joined_at'] = $member->joined_at ?? now();
        }

        $member->update($updates);
        $member->refresh();

        $this->removeDuplicateRows($trip, $member, $email, $phone);

        return [
            'member' => $member,
            'created' => false,
            'registered' => $member->user_id !== null,
            'self' => false,
            'message' => __(':member was already on this trip, details refreshed.', ['member' => $member->name]),
        ];
    }

    /**
     * Cleaning pass for older data: extra rows pointing at the same account or
     * contact details are folded away. Rows with financial history are retired
     * instead of deleted so the ledger stays intact.
     */
    private function removeDuplicateRows(Trip $trip, TripMember $keep, ?string $email, ?string $phone): void
    {
        $duplicates = $trip->members()
            ->whereKeyNot($keep->id)
            ->where(function ($query) use ($keep, $email, $phone) {
                if ($keep->user_id !== null) {
                    $query->orWhere('user_id', $keep->user_id);
                }

                if ($email !== null) {
                    $query->orWhere('email', $email);
                }

                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->get();

        foreach ($duplicates as $duplicate) {
            $hasHistory = $duplicate->shares()->exists()
                || $trip->expenses()->where('trip_member_id', $duplicate->id)->exists()
                || $trip->settlements()
                    ->where(fn ($query) => $query->where('from_member_id', $duplicate->id)->orWhere('to_member_id', $duplicate->id))
                    ->exists();

            if ($hasHistory) {
                $duplicate->update([
                    'status' => MemberStatus::Removed->value,
                    'include_in_split' => false,
                    'user_id' => null,
                ]);
            } else {
                $duplicate->delete();
            }
        }
    }

    /** Links a pending invite row to a real account. */
    public function attach(TripMember $member, User $user, bool $silent = false): TripMember
    {
        // Somebody invited by both email and phone would end up with two rows,
        // and a trip may only hold one row per account (trip_id + user_id).
        $alreadyOnTrip = $member->trip->members()
            ->where('user_id', $user->id)
            ->whereKeyNot($member->id)
            ->first();

        if ($alreadyOnTrip !== null) {
            $hasHistory = $member->shares()->exists()
                || $member->trip->expenses()->where('trip_member_id', $member->id)->exists();

            if ($hasHistory) {
                $member->update([
                    'status' => MemberStatus::Declined->value,
                    'include_in_split' => false,
                    'user_id' => null,
                ]);
            } else {
                $member->delete();
            }

            return $alreadyOnTrip;
        }

        $member->forceFill([
            'user_id' => $user->id,
            'status' => MemberStatus::Active->value,
            'joined_at' => $member->joined_at ?? now(),
            'name' => $member->name ?: $user->name,
        ])->save();

        if (! $silent) {
            $this->logger->log(
                $member->trip,
                'member_joined',
                Phrase::make(':user joined the trip', ['user' => $user->name]),
                $user,
                $member,
            );

            $this->logger->notifyMembers(
                $member->trip,
                Phrase::make(':user joined :trip', ['user' => $user->name, 'trip' => $member->trip->title]),
                Phrase::make('The split just got one traveller bigger.'),
                'success',
                'users',
                [$user->id],
            );
        }

        return $member;
    }

    /**
     * Called right after login/registration: any invite sent to this email or
     * phone before the account existed is claimed here.
     *
     * @return Collection<int, TripMember>
     */
    public function claimPendingFor(User $user): Collection
    {
        // Only through contacts the account has proven it owns: anybody can type
        // somebody else's email or number into the sign up form.
        $pending = TripMember::pendingForContact(...$this->verifiedContacts($user))->get();
        $claimed = collect();

        foreach ($pending as $member) {
            // Never steal a row that is already bound to somebody else.
            if ($member->user_id !== null && $member->user_id !== $user->id) {
                continue;
            }

            if ($member->trip->members()->where('user_id', $user->id)->whereKeyNot($member->id)->exists()) {
                $member->delete();

                continue;
            }

            // Non silent on purpose: this is the moment the traveller actually
            // joins, so the trip timeline should say so. It can only happen once
            // per invite row because the row is bound to the account afterwards.
            $claimed->push($this->attach($member, $user));
        }

        return $claimed;
    }

    public function joinViaCode(Trip $trip, User $user): TripMember
    {
        $existing = $trip->members()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            if ($existing->status === MemberStatus::Removed) {
                $existing->update(['status' => MemberStatus::Active->value]);
            }

            return $existing;
        }

        // A pending invite for the same (verified) contact should be claimed
        // instead of creating a duplicate traveller.
        $pending = TripMember::pendingForContact(...$this->verifiedContacts($user))
            ->where('trip_id', $trip->id)
            ->first();

        if ($pending !== null) {
            return $this->attach($pending, $user);
        }

        $member = $trip->members()->create([
            'user_id' => $user->id,
            'invited_by' => $trip->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => 'member',
            'status' => MemberStatus::Active->value,
            'include_in_split' => true,
            'share_units' => 1,
            'joined_at' => now(),
        ]);

        $this->logger->log($trip, 'member_joined', Phrase::make(':user joined via invite link', ['user' => $user->name]), $user, $member);
        $this->logger->notifyMembers(
            $trip,
            Phrase::make(':user joined :trip', ['user' => $user->name, 'trip' => $trip->title]),
            Phrase::make('Someone used your invite link and joined the split.'),
            'success',
            'users',
            [$user->id],
        );

        return $member;
    }

    /**
     * Applies an invite link for a traveller who just signed in or registered.
     * Idempotent by design: somebody who is already travelling on that trip is
     * simply sent back to it, never added twice.
     *
     * @return array{trip: Trip, joined: bool, ended: bool}|null
     */
    public function acceptInviteCode(User $user, ?string $code): ?array
    {
        if (blank($code)) {
            return null;
        }

        $trip = Trip::where('invite_code', $code)->first();

        if ($trip === null) {
            return null;
        }

        $alreadyTravelling = $trip->members()
            ->where('user_id', $user->id)
            ->where('status', '!=', MemberStatus::Removed->value)
            ->exists();

        if ($alreadyTravelling) {
            return ['trip' => $trip, 'joined' => false, 'ended' => $trip->isEnded()];
        }

        if ($trip->isEnded()) {
            return ['trip' => $trip, 'joined' => false, 'ended' => true];
        }

        $this->joinViaCode($trip, $user);

        return ['trip' => $trip->refresh(), 'joined' => true, 'ended' => false];
    }

    /** @return array{0: ?string, 1: ?string} the email and phone this account has verified */
    private function verifiedContacts(User $user): array
    {
        return [
            $user->hasVerifiedEmail() ? $user->email : null,
            $user->hasVerifiedPhone() ? $user->phone : null,
        ];
    }
}
