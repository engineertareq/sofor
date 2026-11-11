<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\DeviceToken;
use App\Models\Tour;
use App\Models\TravellerDetail;
use App\Models\Trip;
use App\Models\TripCheckin;
use App\Models\TripMember;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Phrase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A traveller deletes their own account, from the app or the website (Google
 * Play requires both).
 *
 * Trips are shared ledgers, so the account is emptied rather than removed:
 * everything that says who they were goes - name, email, phone, password,
 * photo, payout numbers, places, ID details, check-ins, devices, alerts - but
 * their place on trips with other people stays, under the name "Deleted
 * traveller", so nobody else's balance changes. What is left of the user row
 * is an id with nothing behind it; nobody can sign in to it again.
 */
class AccountDeletion
{
    public function __construct(private readonly TripActivityLogger $logger) {}

    /**
     * Why the account cannot be deleted yet, in words for its owner. Empty
     * when it can.
     *
     * @return array<int, string>
     */
    public function blockers(User $user): array
    {
        $blockers = [];

        if ($user->isAdmin()) {
            $blockers[] = __('Administrator accounts are removed from the console by another administrator.');
        }

        // People have booked and may have paid: the tour has to be finished or called off first.
        $running = $user->tours()->get()
            ->filter(fn (Tour $tour) => $tour->status->isActive() && $tour->registrations()->exists());

        foreach ($running as $tour) {
            $blockers[] = __('Your tour ":tour" still has bookings. Complete or cancel it first.', ['tour' => $tour->title]);
        }

        return $blockers;
    }

    public function delete(User $user): void
    {
        $nameless = Lang::get('Deleted traveller', [], (string) config('app.interface_locale', 'bn'));

        DB::transaction(function () use ($user, $nameless) {
            $this->closeTrips($user);

            // Tours nobody booked simply go; finished ones stay for the people who came.
            $user->tours()->get()
                ->reject(fn (Tour $tour) => $tour->registrations()->exists())
                ->each->delete();

            $memberIds = TripMember::where('user_id', $user->id)->pluck('id');

            TravellerDetail::whereIn('trip_member_id', $memberIds)->delete();
            TripCheckin::whereIn('trip_member_id', $memberIds)->delete();

            // Their seat on each remaining trip, with nothing left that identifies them.
            TripMember::whereIn('id', $memberIds)->update([
                'user_id' => null,
                'name' => $nameless,
                'email' => null,
                'phone' => null,
                'invite_token' => null,
                'pay_token' => null,
                'family_token' => null,
                'family_phone' => null,
            ]);

            VerificationCode::where('user_id', $user->id)->delete();
            $user->payoutMethods()->delete();
            $user->visitedPlaces()->delete();
            $user->notifications()->delete();
            $user->tokens()->delete();
            DeviceToken::where('user_id', $user->id)->delete();
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            app(AvatarService::class)->remove($user);

            $user->forceFill([
                'name' => $nameless,
                'username' => 'deleted_'.$user->id.'_'.Str::lower(Str::random(6)),
                'email' => null,
                'phone' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'travel_profile' => null,
                'profile_public' => false,
                'is_active' => false,
                'sms_balance' => 0,
                'deleted_at' => now(),
            ])->save();
        });
    }

    /**
     * Trips this traveller leads. One nobody else can open is deleted. One
     * with other travellers is kept for them and, if still running, ended:
     * with its leader gone nobody could manage it, and the final dues stand.
     */
    private function closeTrips(User $user): void
    {
        foreach ($user->ownedTrips()->get() as $trip) {
            /** @var Trip $trip */
            $shared = $trip->members()
                ->whereNotNull('user_id')
                ->where('user_id', '!=', $user->id)
                ->where('status', '!=', MemberStatus::Removed->value)
                ->exists();

            if (! $shared) {
                $trip->delete();

                continue;
            }

            if ($trip->isActive()) {
                $trip->forceFill(['status' => 'ended', 'ended_at' => now()])->save();

                $this->logger->log($trip, 'trip_ended', Phrase::make('The trip ended because its leader closed their account'));
                $this->logger->notifyMembers(
                    $trip,
                    Phrase::make(':trip has ended', ['trip' => $trip->title]),
                    Phrase::make('The trip leader closed their Sofor account. Costs are now locked - settle the remaining dues.'),
                    'warning',
                    'lock',
                    [$user->id],
                );
            }
        }
    }
}
