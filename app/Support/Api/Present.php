<?php

namespace App\Support\Api;

use App\Models\Expense;
use App\Models\PayoutMethod;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use App\Support\Phone;
use App\Support\Poisha;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * The shapes the app receives. One place, so a field means the same thing on
 * every endpoint: money is whole poisha, a day is YYYY-MM-DD, a moment is ISO
 * 8601 in UTC, and digits are always plain (the app writes them in Bangla).
 */
class Present
{
    public static function moment(?Carbon $at): ?string
    {
        return $at?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public static function day(?Carbon $on): ?string
    {
        return $on?->format('Y-m-d');
    }

    /** A profile photo, served to the app with its sign in token. */
    public static function avatar(?User $user): ?string
    {
        return $user?->avatar_path !== null
            ? route('api.avatars.show', basename($user->avatar_path))
            : null;
    }

    /**
     * The signed in traveller's own account.
     *
     * @return array<string, mixed>
     */
    public static function account(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_display' => Phone::pretty($user->phone),
            'email_verified' => $user->hasVerifiedEmail(),
            'phone_verified' => $user->hasVerifiedPhone(),
            'verified' => $user->hasVerifiedContact(),
            'locale' => $user->preferredLocale(),
            'initials' => $user->initials(),
            'avatar_url' => self::avatar($user),
            'profile_public' => (bool) $user->profile_public,
            'public_url' => $user->publicUrl(),
            'sms_balance' => Poisha::of($user->sms_balance),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'created_at' => self::moment($user->created_at),
        ];
    }

    /**
     * A trip as a card in a list. `$mine` is this traveller's row from
     * PortfolioService, so a list of trips costs a handful of queries, not one
     * ledger each.
     *
     * @param  array<string, float|int>|null  $mine
     * @return array<string, mixed>
     */
    public static function trip(Trip $trip, User $viewer, ?array $mine = null): array
    {
        $perPerson = (float) $trip->budget_per_person > 0;

        return [
            'id' => $trip->id,
            'title' => $trip->title,
            'destination' => $trip->destination,
            'description' => $trip->description,
            'cover' => array_key_exists((string) $trip->cover, Trip::COVERS) ? $trip->cover : 'aurora',
            'icon' => $trip->icon,
            'currency' => $trip->currency,
            'status' => $trip->status->value,
            'starts_on' => self::day($trip->starts_on),
            'ends_on' => self::day($trip->ends_on),
            'ended_at' => self::moment($trip->ended_at),
            'budget' => $trip->hasBudget() ? Poisha::of($perPerson ? $trip->budget_per_person : $trip->budget) : null,
            'budget_mode' => $trip->hasBudget() ? ($perPerson ? 'person' : 'group') : null,
            'is_owner' => $trip->user_id === $viewer->id,
            'owner_name' => $trip->owner?->name,
            'member_count' => $trip->relationLoaded('members')
                ? $trip->members->filter(fn (TripMember $member) => $member->isTravelling())->count()
                : $trip->members()->whereIn('status', ['active', 'invited'])->count(),
            'expense_count' => (int) ($trip->expenses_count ?? $trip->expenses()->count()),
            'spent' => Poisha::of($trip->expenses_sum_amount ?? $trip->expenses()->sum('amount')),
            'my_due' => Poisha::of($mine['due'] ?? 0),
            'my_credit' => Poisha::of($mine['credit'] ?? 0),
            'invite_code' => $trip->invite_code,
            'invite_url' => $trip->inviteUrl(),
            'last_activity_at' => self::moment($trip->last_activity_at),
            'updated_at' => self::moment($trip->updated_at),
        ];
    }

    /**
     * A traveller on a trip, with where they stand. `$balance` is their row
     * from TripLedger.
     *
     * @param  array<string, mixed>  $balance
     * @return array<string, mixed>
     */
    public static function member(TripMember $member, User $viewer, array $balance = []): array
    {
        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->name,
            'role' => $member->role->value,
            'status' => $member->status->value,
            'share_units' => (int) $member->share_units,
            'include_in_split' => (bool) $member->include_in_split,
            'is_me' => $member->user_id === $viewer->id,
            'is_registered' => $member->isRegistered(),
            'contact' => $member->email ?? Phone::pretty($member->phone),
            'initials' => $member->initials(),
            'avatar_url' => self::avatar($member->user),
            'paid' => Poisha::of($balance['paid'] ?? 0),
            'share' => Poisha::of($balance['share'] ?? 0),
            'sent' => Poisha::of($balance['sent'] ?? 0),
            'received' => Poisha::of($balance['received'] ?? 0),
            'balance' => Poisha::of($balance['balance'] ?? 0),
            // Where to send what they are owed, for the people paying them.
            'payout_methods' => $member->user !== null
                ? $member->user->payoutMethods->map(fn (PayoutMethod $method) => self::payoutMethod($method))->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    public static function payoutMethod(PayoutMethod $method): array
    {
        return [
            'id' => $method->id,
            'provider' => $method->provider->value,
            'label' => $method->provider->label(),
            'number' => $method->copyValue(),
            'display_number' => $method->displayNumber(),
            'account_name' => $method->account_name,
            'detail' => $method->detail(),
            'is_wallet' => $method->provider->isMobileWallet(),
            'is_primary' => (bool) $method->is_primary,
        ];
    }

    /** @return array<string, mixed> */
    public static function expense(Expense $expense, bool $canModify = false): array
    {
        return [
            'id' => $expense->id,
            'client_uuid' => $expense->client_uuid,
            'trip_id' => $expense->trip_id,
            'title' => $expense->title,
            'category' => $expense->categoryEnum()->value,
            'amount' => Poisha::of($expense->amount),
            'spent_on' => self::day($expense->spent_on),
            'split_type' => $expense->split_type->value,
            'notes' => $expense->notes,
            'payer_member_id' => $expense->trip_member_id,
            'created_by' => $expense->created_by,
            'shares' => $expense->shares->map(fn ($share) => [
                'member_id' => $share->trip_member_id,
                'amount' => Poisha::of($share->amount),
            ])->all(),
            'items' => collect($expense->items ?? [])->map(fn (array $item) => [
                'name' => (string) ($item['name'] ?? ''),
                'price' => Poisha::of($item['price'] ?? 0),
                'members' => array_map('intval', (array) ($item['members'] ?? [])),
            ])->all(),
            'receipt_url' => $expense->hasReceipt()
                ? route('api.trips.expenses.receipt', [$expense->trip_id, $expense->id, 'v' => substr(md5((string) $expense->receipt_path), 0, 8)])
                : null,
            'can_modify' => $canModify,
            'created_at' => self::moment($expense->created_at),
            'updated_at' => self::moment($expense->updated_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function settlement(Settlement $settlement, bool $canConfirm = false, bool $canDelete = false): array
    {
        return [
            'id' => $settlement->id,
            'client_uuid' => $settlement->client_uuid,
            'trip_id' => $settlement->trip_id,
            'from_member_id' => $settlement->from_member_id,
            'to_member_id' => $settlement->to_member_id,
            'amount' => Poisha::of($settlement->amount),
            'method' => $settlement->methodEnum()->value,
            'reference' => $settlement->reference,
            'note' => $settlement->note,
            'settled_on' => self::day($settlement->settled_on),
            'pending' => $settlement->isPending(),
            'confirmed_at' => self::moment($settlement->confirmed_at),
            'recorded_by' => $settlement->recorded_by,
            'can_confirm' => $canConfirm,
            'can_delete' => $canDelete,
            'created_at' => self::moment($settlement->created_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function notification(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'tone' => (string) ($data['tone'] ?? 'info'),
            'icon' => (string) ($data['icon'] ?? 'bell'),
            'trip_id' => isset($data['trip_id']) ? (int) $data['trip_id'] : null,
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => self::moment($notification->created_at),
        ];
    }
}
