<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\PayoutProvider;
use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use App\Models\Trip;
use App\Services\CrossTripBalances;
use App\Services\PayoutMethods;
use App\Support\Api\Present;
use App\Support\Glyphs;
use App\Support\Poisha;
use App\Support\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What sits around the trips: the lists the app's forms offer, where friends
 * pay this traveller, what they owe each friend over every trip, and alerts.
 */
class AccountController extends Controller
{
    /**
     * Open to everyone: which builds are still supported, and the choices
     * behind the app's pickers, named in the language it asked for.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'min_version_code' => (int) config('services.android.min_version_code'),
            'latest_version_code' => (int) config('services.android.latest_version_code'),
            'currencies' => ['BDT', 'USD', 'EUR', 'GBP', 'INR', 'AED', 'MYR', 'SGD', 'THB'],
            'covers' => collect(Trip::COVERS)->keys()->map(fn (string $key) => ['key' => $key, 'label' => Trip::coverLabel($key)])->all(),
            'icons' => Glyphs::TRIP,
            'categories' => collect(ExpenseCategory::cases())->map(fn (ExpenseCategory $case) => ['key' => $case->value, 'label' => $case->label()])->all(),
            'payment_methods' => collect(PaymentMethod::payable())->map(fn (PaymentMethod $case) => ['key' => $case->value, 'label' => $case->label()])->all(),
            'payout_providers' => collect(PayoutProvider::cases())->map(fn (PayoutProvider $case) => [
                'key' => $case->value,
                'label' => $case->label(),
                'is_wallet' => $case->isMobileWallet(),
            ])->all(),
            'links' => [
                'privacy' => route('privacy'),
                'terms' => route('terms'),
                'delete_account' => route('account.delete'),
            ],
        ]);
    }

    /** Whether a username is free, for the profile form. */
    public function username(Request $request): JsonResponse
    {
        $username = Username::normalize($request->query('username'));
        $validator = validator(['username' => $username], ['username' => Username::rules($request->user())], Username::messages());

        return response()->json([
            'username' => $username,
            'available' => ! $validator->fails(),
            'message' => $validator->errors()->first('username') ?: null,
        ]);
    }

    /* ------------------------------------------------------------------
       How friends pay you
    ------------------------------------------------------------------ */

    public function payoutMethods(Request $request): JsonResponse
    {
        return $this->payoutList($request);
    }

    public function addPayoutMethod(Request $request, PayoutMethods $methods): JsonResponse
    {
        $method = $methods->add($request);

        return $this->payoutList($request, __(':label :display_number added. Friends will see it when they owe you.', ['label' => $method->provider->label(), 'display_number' => $method->displayNumber()]), 201);
    }

    public function primaryPayoutMethod(Request $request, PayoutMethod $payoutMethod, PayoutMethods $methods): JsonResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $methods->makePrimary($request->user(), $payoutMethod);

        return $this->payoutList($request, __(':label :display_number is now your main account.', ['label' => $payoutMethod->provider->label(), 'display_number' => $payoutMethod->displayNumber()]));
    }

    public function removePayoutMethod(Request $request, PayoutMethod $payoutMethod, PayoutMethods $methods): JsonResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $methods->remove($request->user(), $payoutMethod);

        return $this->payoutList($request, __(':label account removed.', ['label' => $payoutMethod->provider->label()]));
    }

    private function payoutList(Request $request, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'payout_methods' => $request->user()->payoutMethods()->get()->map(fn (PayoutMethod $method) => Present::payoutMethod($method))->all(),
        ] + ($message !== null ? ['message' => $message] : []), $status);
    }

    /* ------------------------------------------------------------------
       Balances with each friend, over every trip
    ------------------------------------------------------------------ */

    public function balances(Request $request, CrossTripBalances $balances): JsonResponse
    {
        return response()->json([
            'friends' => $balances->forUser($request->user())->map(fn (array $row) => [
                'key' => $row['key'],
                'name' => $row['name'],
                'user_id' => $row['user']?->id,
                'avatar_url' => Present::avatar($row['user']),
                'currency' => $row['currency'],
                // Positive: they owe this traveller. Negative: this traveller owes them.
                'net' => Poisha::of($row['net']),
                'payout_methods' => $row['user'] !== null
                    ? $row['user']->payoutMethods->map(fn (PayoutMethod $method) => Present::payoutMethod($method))->all()
                    : [],
                'items' => collect($row['items'])->map(fn (array $item) => [
                    'trip_id' => $item['trip']->id,
                    'trip_title' => $item['trip']->title,
                    'amount' => Poisha::of($item['amount']),
                    'you_owe' => $item['you_owe'],
                    'from_member_id' => $item['from']->id,
                    'to_member_id' => $item['to']->id,
                ])->all(),
            ])->all(),
        ]);
    }

    /* ------------------------------------------------------------------
       Alerts
    ------------------------------------------------------------------ */

    /** Cursor paging, so alerts that arrive while the list is open never repeat a row. */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = $user->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn ($notification) => Present::notification($notification))->all(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function readNotification(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }
}
