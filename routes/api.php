<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The Sofor Android app: /api/v1
|--------------------------------------------------------------------------
| JSON only. Signed in with a Sanctum token (Authorization: Bearer ...).
| Money is whole poisha, days are YYYY-MM-DD, moments are ISO 8601 in UTC.
| The rules, limits and numbers are the website's own: these controllers
| call the same requests, policies and services. See ANDROID_APP_PLAN.md.
*/

Route::name('api.')->group(function () {
    Route::get('/config', [AccountController::class, 'config'])->middleware('throttle:60,1')->name('config');

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('auth.login');
    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('auth.password.forgot');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('auth.password.reset');

    /*
    | Signed in, verified or not: enough to finish verifying, fix the account
    | or leave.
    */
    Route::middleware(['auth:sanctum', 'api.traveller'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/me', [MeController::class, 'show'])->name('me');
        Route::put('/me', [MeController::class, 'update'])->middleware('throttle:20,1')->name('me.update');
        Route::put('/me/locale', [MeController::class, 'locale'])->name('me.locale');
        // Checks the current password, so it must not become a way to guess it.
        Route::put('/me/password', [MeController::class, 'password'])->middleware('throttle:6,1')->name('me.password');
        Route::delete('/me', [MeController::class, 'destroy'])->middleware('throttle:6,1')->name('me.destroy');

        Route::post('/me/email/send', [MeController::class, 'sendEmailCode'])->middleware('throttle:6,1')->name('me.email.send');
        Route::post('/me/email/verify', [MeController::class, 'verifyEmail'])->middleware('throttle:12,1')->name('me.email.verify');
        Route::post('/me/phone/send', [MeController::class, 'sendPhoneCode'])->middleware('throttle:6,1')->name('me.phone.send');
        Route::post('/me/phone/verify', [MeController::class, 'verifyPhone'])->middleware('throttle:12,1')->name('me.phone.verify');
        Route::put('/me/contact', [MeController::class, 'correctContact'])->middleware('throttle:6,1')->name('me.contact');

        Route::get('/usernames/check', [AccountController::class, 'username'])->middleware('throttle:60,1')->name('usernames.check');
    });

    /*
    | The traveller app proper: one proven email or phone is needed first.
    */
    Route::middleware(['auth:sanctum', 'api.traveller', 'api.verified'])->group(function () {
        Route::post('/me/avatar', [MeController::class, 'avatar'])->middleware('throttle:10,1')->name('me.avatar');
        Route::delete('/me/avatar', [MeController::class, 'removeAvatar'])->name('me.avatar.destroy');
        Route::get('/avatars/{file}', AvatarController::class)->where('file', '[0-9]+-[A-Za-z0-9]{24}\.webp')->name('avatars.show');

        Route::post('/devices', [MeController::class, 'registerDevice'])->middleware('throttle:30,1')->name('devices.store');
        Route::delete('/devices', [MeController::class, 'forgetDevice'])->name('devices.destroy');

        Route::get('/payout-methods', [AccountController::class, 'payoutMethods'])->name('payout-methods.index');
        Route::post('/payout-methods', [AccountController::class, 'addPayoutMethod'])->middleware('throttle:20,1')->name('payout-methods.store');
        Route::post('/payout-methods/{payoutMethod}/primary', [AccountController::class, 'primaryPayoutMethod'])->name('payout-methods.primary');
        Route::delete('/payout-methods/{payoutMethod}', [AccountController::class, 'removePayoutMethod'])->name('payout-methods.destroy');

        Route::get('/balances', [AccountController::class, 'balances'])->middleware('throttle:60,1')->name('balances');

        Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read-all', [AccountController::class, 'readAllNotifications'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [AccountController::class, 'readNotification'])->name('notifications.read');

        // Public codes, so they are rate limited: they cannot be guessed by trying them.
        Route::get('/join/{code}', [TripController::class, 'invite'])->where('code', '[A-Za-z0-9]{4,32}')->middleware('throttle:30,1')->name('join.show');
        Route::post('/join/{code}', [TripController::class, 'join'])->where('code', '[A-Za-z0-9]{4,32}')->middleware('throttle:20,1')->name('join');

        Route::prefix('trips')->name('trips.')->scopeBindings()->group(function () {
            Route::get('/', [TripController::class, 'index'])->name('index');
            Route::post('/', [TripController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::get('/{trip}', [TripController::class, 'show'])->name('show');
            Route::put('/{trip}', [TripController::class, 'update'])->name('update');
            Route::delete('/{trip}', [TripController::class, 'destroy'])->name('destroy');
            Route::post('/{trip}/end', [TripController::class, 'end'])->name('end');
            Route::post('/{trip}/leave', [TripController::class, 'leave'])->name('leave');
            Route::post('/{trip}/invite/rotate', [TripController::class, 'rotateInvite'])->middleware('throttle:10,1')->name('invite.rotate');

            Route::post('/{trip}/members', [TripController::class, 'addMember'])->middleware('throttle:30,1')->name('members.store');
            Route::put('/{trip}/members/{member}', [TripController::class, 'updateMember'])->name('members.update');
            Route::delete('/{trip}/members/{member}', [TripController::class, 'removeMember'])->name('members.destroy');
            Route::post('/{trip}/members/{member}/remind', [TripController::class, 'remind'])->middleware('throttle:20,1')->name('members.remind');

            Route::post('/{trip}/expenses', [LedgerController::class, 'storeExpense'])->middleware('throttle:60,1')->name('expenses.store');
            // POST as well as PUT: a receipt photo travels as a multipart form, which PHP only reads on POST.
            Route::match(['put', 'post'], '/{trip}/expenses/{expense}', [LedgerController::class, 'updateExpense'])->name('expenses.update');
            Route::delete('/{trip}/expenses/{expense}', [LedgerController::class, 'destroyExpense'])->name('expenses.destroy');
            Route::get('/{trip}/expenses/{expense}/receipt', ReceiptController::class)->middleware('throttle:120,1')->name('expenses.receipt');

            Route::post('/{trip}/settlements', [LedgerController::class, 'storeSettlement'])->middleware('throttle:30,1')->name('settlements.store');
            Route::post('/{trip}/settlements/{settlement}/confirm', [LedgerController::class, 'confirmSettlement'])->name('settlements.confirm');
            Route::post('/{trip}/settlements/{settlement}/reject', [LedgerController::class, 'rejectSettlement'])->name('settlements.reject');
            Route::delete('/{trip}/settlements/{settlement}', [LedgerController::class, 'destroySettlement'])->name('settlements.destroy');
        });
    });
});
