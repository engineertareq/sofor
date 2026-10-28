<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DonationController as AdminDonationController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SponsorController as AdminSponsorController;
use App\Http\Controllers\Admin\SponsorInquiryController as AdminSponsorInquiryController;
use App\Http\Controllers\Admin\TripController as AdminTripController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BalancesController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExplorerController;
use App\Http\Controllers\FamilyLinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapMakerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayLinkController;
use App\Http\Controllers\PayoutMethodController;
use App\Http\Controllers\TripReminderController;
use App\Http\Controllers\PastTravellerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SmsWalletController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TravelMapController;
use App\Http\Controllers\TripCollectionController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripRecapController;
use App\Http\Controllers\TripTimelineController;
use App\Http\Controllers\TripSafetyController;
use App\Http\Controllers\TripManifestController;
use App\Http\Controllers\TripPollController;
use App\Http\Controllers\TripEstimateController;
use App\Http\Controllers\TripExpenseController;
use App\Http\Controllers\TripJoinController;
use App\Http\Controllers\TripMemberController;
use App\Http\Controllers\TripSettlementController;
use App\Http\Controllers\UsernameController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

// The public map maker: mark places and download a picture, no account needed.
Route::get('/travel-map/{map?}', MapMakerController::class)->whereIn('map', array_keys(config('travel_maps.maps')))->name('map-maker');

// The free trip split calculator: who paid what in, who pays whom out. Nothing is stored.
Route::view('/trip-split-calculator', 'tools.split-calculator')->name('split-calculator');

// For search engines; built here so they always carry the real site address.
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::view('/about', 'pages.about')->name('about');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// Branding uploaded from Admin -> Settings. Public, because the logo and favicon
// show on every page; the strict pattern keeps paths inside the branding folder.
Route::get('/branding/{file}', [BrandingController::class, 'asset'])
    ->where('file', '(logo|favicon)-[A-Za-z0-9]{24}(-[0-9]+)?\.png')
    ->name('branding.asset');
Route::get('/app.webmanifest', [BrandingController::class, 'manifest'])->name('manifest');

// Sponsors: who backs Sofor, what sponsoring gets you, and a form to get in touch.
Route::get('/sponsors', [SponsorController::class, 'index'])->name('sponsors');
Route::post('/sponsors/inquiries', [SponsorController::class, 'storeInquiry'])
    ->middleware('throttle:5,1')
    ->name('sponsors.inquiries.store');
Route::get('/sponsors/logos/{file}', [SponsorController::class, 'logo'])
    ->where('file', '[A-Za-z0-9]{24}\.png')
    ->name('sponsors.logo');

// The Explorers leaderboard: public profiles ranked by how much they have seen.
Route::get('/explorers', [ExplorerController::class, 'index'])->name('explorers');

// Public traveller profiles, sofor.io/@username, with their travel maps.
Route::get('/@{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9_]{1,40}')
    ->name('profile.public');

// "Is this username free?" while signing up or editing the profile.
Route::get('/username/check', [UsernameController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('username.check');

/*
|--------------------------------------------------------------------------
| Support Sofor
| Anyone - signed in or not - can chip in through bKash Checkout.
|--------------------------------------------------------------------------
*/
Route::get('/support', [SupportController::class, 'index'])->name('support.index');
Route::get('/support/thanks', [SupportController::class, 'thanks'])->name('support.thanks');
Route::post('/support/start', [SupportController::class, 'start'])
    ->middleware('throttle:12,1')
    ->name('support.start');
Route::get('/support/callback', [SupportController::class, 'callback'])->middleware('throttle:20,1')->name('support.callback');

// The half of the flow bKash's Checkout JS drives from the browser.
Route::post('/support/bkash/create', [SupportController::class, 'create'])
    ->middleware('throttle:12,1')
    ->name('support.bkash.create');
Route::post('/support/bkash/execute', [SupportController::class, 'execute'])
    ->middleware('throttle:20,1')
    ->name('support.bkash.execute');

/*
|--------------------------------------------------------------------------
| Guest authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    // Forgotten password: a link by email, or a texted code by phone.
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/phone', [PasswordResetController::class, 'phone'])->name('password.phone');
    Route::post('/reset-password/phone', [PasswordResetController::class, 'updatePhone'])->middleware('throttle:10,1')->name('password.phone.update');
    Route::post('/reset-password/phone/resend', [PasswordResetController::class, 'resendPhone'])->middleware('throttle:3,1')->name('password.phone.resend');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Verification
| A new account confirms its email or phone with a six digit code (emailed
| or texted) before the traveller app opens; see EnsureContactIsVerified.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('verify')->name('verification.')->group(function () {
    Route::get('/', [VerificationController::class, 'notice'])->name('notice');
    Route::post('/email/send', [VerificationController::class, 'sendEmailCode'])->middleware('throttle:6,1')->name('send');
    Route::post('/email', [VerificationController::class, 'verifyEmail'])->middleware('throttle:12,1')->name('email.verify');
    Route::post('/phone/send', [VerificationController::class, 'sendPhoneCode'])->middleware('throttle:6,1')->name('phone.send');
    Route::post('/phone', [VerificationController::class, 'verifyPhone'])->middleware('throttle:12,1')->name('phone.verify');
    Route::put('/contact', [VerificationController::class, 'updateContact'])->middleware('throttle:6,1')->name('contact.update');
});

/*
|--------------------------------------------------------------------------
| Invite links stay public so the preview can be shared before sign up
|--------------------------------------------------------------------------
*/
// Pay links: a traveller's own page of what they owe and where to send it, for
// people without an account. Unguessable, rate limited, never indexed.
Route::get('/p/{token}', [PayLinkController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{12}')
    ->middleware('throttle:30,1')
    ->name('pay.show');
Route::post('/p/{token}/paid', [PayLinkController::class, 'paid'])
    ->where('token', '[A-Za-z0-9]{12}')
    ->middleware('throttle:6,1')
    ->name('pay.paid');

// English or Bangla.
Route::post('/locale', LocaleController::class)->middleware('throttle:20,1')->name('locale');


// Shown by the service worker when a page is needed and there is no connection.
Route::view('/offline', 'offline')->name('offline');

// Family links: a traveller's private page for the people at home - the trip
// and its check-ins, no account needed. Unguessable, rate limited, never indexed.
Route::get('/f/{token}', [FamilyLinkController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20}')
    ->middleware('throttle:30,1')
    ->name('family.show');

// A trip's cost timeline, shared by its leader. Unguessable, switchable, never indexed.
Route::get('/timeline/{token}', [TripTimelineController::class, 'public'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:60,1')
    ->name('timeline.public');

// Public by design, so it is rate limited: the codes cannot be guessed by trying them.
Route::get('/join/{code}', [TripJoinController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{4,32}')
    ->middleware('throttle:30,1')
    ->name('trips.join.show');
Route::post('/join/{code}', [TripJoinController::class, 'store'])
    ->middleware(['auth', 'traveller', 'verified.contact', 'throttle:20,1'])
    ->name('trips.join');

/*
|--------------------------------------------------------------------------
| Traveller app
| Admin accounts are sent to the console instead, see EnsureUserIsTraveller.
| Accounts that have not verified an email or phone yet go to /verify.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'traveller', 'verified.contact'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Sofor is free: there is no trip limit and nothing to buy. Old links to
    // the trip pass page land on a new trip instead of a 404.
    Route::redirect('/pass', '/trips/create', 301);

    Route::prefix('trips')->name('trips.')->group(function () {
        Route::get('/', [TripController::class, 'index'])->name('index');
        Route::get('/create', [TripController::class, 'create'])->name('create');
        Route::post('/', [TripController::class, 'store'])->middleware('throttle:20,1')->name('store');

        // What trips to a place usually cost, for the new-trip form.
        Route::get('/estimate', TripEstimateController::class)
            ->middleware('throttle:60,1')
            ->name('estimate');

        // Travellers from earlier trips, searched by name or email while a new trip is filled.
        Route::get('/past-travellers', PastTravellerController::class)
            ->middleware('throttle:60,1')
            ->name('past-travellers');

        Route::get('/{trip}', [TripController::class, 'show'])->name('show');
        Route::get('/{trip}/edit', [TripController::class, 'edit'])->name('edit');
        Route::put('/{trip}', [TripController::class, 'update'])->name('update');
        Route::delete('/{trip}', [TripController::class, 'destroy'])->name('destroy');
        Route::post('/{trip}/end', [TripController::class, 'end'])->name('end');
        Route::post('/{trip}/leave', [TripController::class, 'leave'])->name('leave');
        Route::post('/{trip}/invite/rotate', [TripController::class, 'regenerateInvite'])->middleware('throttle:10,1')->name('invite.rotate');

        // A real, downloadable PDF of the whole ledger - not the browser's print dialog.
        // Rendering a PDF is heavy, so it is rate limited.
        Route::get('/{trip}/summary.pdf', [TripController::class, 'summary'])->middleware('throttle:10,1')->name('summary');

        // Realtime: JSON fragments for patching + server-sent event stream
        // A stream holds a server worker for up to 45 seconds, so a client may
        // not open them faster than it needs to reconnect.
        Route::get('/{trip}/live', [TripController::class, 'live'])->middleware('throttle:60,1')->name('live');
        Route::get('/{trip}/stream', [TripController::class, 'stream'])->middleware('throttle:12,1')->name('stream');

        Route::post('/{trip}/expenses', [TripExpenseController::class, 'store'])->middleware('throttle:60,1')->name('expenses.store');
        Route::put('/{trip}/expenses/{expense}', [TripExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/{trip}/expenses/{expense}', [TripExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/{trip}/expenses/{expense}/receipt', ReceiptController::class)->middleware('throttle:120,1')->name('expenses.receipt');

        Route::post('/{trip}/members', [TripMemberController::class, 'store'])->middleware('throttle:30,1')->name('members.store');
        Route::put('/{trip}/members/{member}', [TripMemberController::class, 'update'])->name('members.update');
        Route::delete('/{trip}/members/{member}', [TripMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('/{trip}/members/{member}/remind', [TripMemberController::class, 'reinvite'])->middleware('throttle:10,1')->name('members.remind');

        // Payments go person to person (each traveller's own bKash, Nagad,
        // bank...). Sofor records them; the receiver confirms what the payer reports.
        Route::post('/{trip}/settlements', [TripSettlementController::class, 'store'])->middleware('throttle:30,1')->name('settlements.store');
        Route::post('/{trip}/remind', [TripReminderController::class, 'storeAll'])->middleware('throttle:6,1')->name('remind.all');
        Route::post('/{trip}/remind/{member}', [TripReminderController::class, 'store'])->middleware('throttle:20,1')->name('remind');
        Route::put('/{trip}/remind/settings', [TripReminderController::class, 'settings'])->name('remind.settings');
        Route::post('/{trip}/settlements/{settlement}/confirm', [TripSettlementController::class, 'confirm'])->name('settlements.confirm');
        Route::post('/{trip}/settlements/{settlement}/reject', [TripSettlementController::class, 'reject'])->name('settlements.reject');
        Route::delete('/{trip}/settlements/{settlement}', [TripSettlementController::class, 'destroy'])->name('settlements.destroy');

        // Advance collections: so much per traveller, paid to one person.
        Route::post('/{trip}/collections', [TripCollectionController::class, 'store'])->middleware('throttle:10,1')->name('collections.store');
        Route::post('/{trip}/collections/{collection}/toggle', [TripCollectionController::class, 'toggle'])->name('collections.toggle');
        Route::post('/{trip}/collections/{collection}/nudge', [TripCollectionController::class, 'nudge'])->middleware('throttle:6,1')->name('collections.nudge');
        Route::delete('/{trip}/collections/{collection}', [TripCollectionController::class, 'destroy'])->name('collections.destroy');

        // Polls and who is coming.
        Route::post('/{trip}/polls', [TripPollController::class, 'store'])->middleware('throttle:10,1')->name('polls.store');
        Route::post('/{trip}/polls/{poll}/vote', [TripPollController::class, 'vote'])->middleware('throttle:60,1')->name('polls.vote');
        Route::post('/{trip}/polls/{poll}/close', [TripPollController::class, 'close'])->name('polls.close');
        Route::delete('/{trip}/polls/{poll}', [TripPollController::class, 'destroy'])->name('polls.destroy');
        Route::put('/{trip}/rsvp', [TripPollController::class, 'rsvp'])->middleware('throttle:30,1')->name('rsvp');

        // The checkpoint list: ID details for army and police checkpoints and hotels.
        Route::get('/{trip}/recap', TripRecapController::class)->name('recap');

        // The cost timeline, and its public link (the leader turns it on and off).
        Route::get('/{trip}/timeline', [TripTimelineController::class, 'show'])->name('timeline');
        Route::put('/{trip}/timeline/share', [TripTimelineController::class, 'share'])->middleware('throttle:20,1')->name('timeline.share');
        Route::post('/{trip}/timeline/share/new', [TripTimelineController::class, 'rotate'])->middleware('throttle:10,1')->name('timeline.rotate');
        Route::get('/{trip}/checkpoint-list', [TripManifestController::class, 'show'])->name('manifest');
        Route::get('/{trip}/checkpoint-list.pdf', [TripManifestController::class, 'pdf'])->middleware('throttle:10,1')->name('manifest.pdf');
        Route::put('/{trip}/checkpoint-list/me', [TripManifestController::class, 'saveMine'])->middleware('throttle:20,1')->name('manifest.mine');
        Route::delete('/{trip}/checkpoint-list/me', [TripManifestController::class, 'destroyMine'])->name('manifest.forget');
        Route::put('/{trip}/checkpoint-list/trip', [TripManifestController::class, 'saveTrip'])->name('manifest.trip');
        Route::put('/{trip}/checkpoint-list/members/{member}', [TripManifestController::class, 'saveFor'])->middleware('throttle:30,1')->name('manifest.member');
        Route::post('/{trip}/checkpoint-list/remind', [TripManifestController::class, 'remind'])->middleware('throttle:6,1')->name('manifest.remind');

        // Check-ins and family links.
        Route::post('/{trip}/checkins', [TripSafetyController::class, 'checkin'])->middleware('throttle:20,1')->name('checkins.store');
        Route::delete('/{trip}/checkins/{checkin}', [TripSafetyController::class, 'destroyCheckin'])->name('checkins.destroy');
        Route::post('/{trip}/family-link', [TripSafetyController::class, 'familyLink'])->middleware('throttle:10,1')->name('family.link');
        Route::delete('/{trip}/family-link', [TripSafetyController::class, 'disableFamilyLink'])->name('family.disable');
        Route::put('/{trip}/family-link/phone', [TripSafetyController::class, 'familyPhone'])->middleware('throttle:10,1')->name('family.phone');
    });

    // A fresh CSRF token for costs queued offline: the page they were typed on may be hours old.
    Route::get('/session/token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'))
        ->middleware('throttle:30,1')
        ->name('session.token');

    // Where you stand with each friend over every shared trip, and settling it in one go.
    Route::get('/balances', [BalancesController::class, 'index'])->name('balances.index');
    Route::post('/balances/{friend}/settle', [BalancesController::class, 'settle'])->middleware('throttle:10,1')->name('balances.settle');

    // SMS wallet: prepaid credit for texts a traveller sends to their group,
    // topped up through Sofor's bKash merchant account.
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [SmsWalletController::class, 'index'])->name('index');
        Route::post('/topup', [SmsWalletController::class, 'start'])->middleware('throttle:10,1')->name('topup');
        Route::get('/callback', [SmsWalletController::class, 'callback'])->middleware('throttle:20,1')->name('callback');
        Route::post('/bkash/create', [SmsWalletController::class, 'create'])->middleware('throttle:10,1')->name('bkash.create');
        Route::post('/bkash/execute', [SmsWalletController::class, 'execute'])->middleware('throttle:20,1')->name('bkash.execute');
    });

    // "How friends pay you": the traveller's own wallets and bank accounts.
    Route::post('/payout-methods', [PayoutMethodController::class, 'store'])->middleware('throttle:20,1')->name('payout-methods.store');
    Route::post('/payout-methods/{payoutMethod}/primary', [PayoutMethodController::class, 'primary'])->name('payout-methods.primary');
    Route::delete('/payout-methods/{payoutMethod}', [PayoutMethodController::class, 'destroy'])->name('payout-methods.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Travel map: the districts and countries a traveller has been to. Maps
    // come from config/travel_maps.php, so a new country needs no new routes.
    Route::prefix('map')->name('travel.')->group(function () {
        $maps = array_keys(config('travel_maps.maps'));

        Route::get('/', [TravelMapController::class, 'index'])->name('index');
        Route::post('/trips/{trip}', [TravelMapController::class, 'fromTrip'])->name('from-trip');
        Route::get('/{map}', [TravelMapController::class, 'show'])->whereIn('map', $maps)->name('show');
        Route::post('/{map}/places', [TravelMapController::class, 'store'])
            ->whereIn('map', $maps)
            ->middleware('throttle:120,1')
            ->name('places.store');
        Route::delete('/{map}/places', [TravelMapController::class, 'clear'])->whereIn('map', $maps)->name('places.clear');
        Route::delete('/{map}/places/{area}', [TravelMapController::class, 'destroy'])
            ->whereIn('map', $maps)
            ->where('area', '[A-Za-z0-9-]{2,12}')
            ->middleware('throttle:120,1')
            ->name('places.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Account settings
| Shared by both sides: travellers post here from /profile, admins from
| /admin/profile, and every action goes back to the page it came from.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');
    // Checks the current password, so it must not become a way to guess it.
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:10,1')
        ->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::put('/profile/avatar/crop', [ProfileController::class, 'cropAvatar'])
        ->middleware('throttle:20,1')
        ->name('profile.avatar.crop');
    // The whole photo behind the square, for its owner's cropper only.
    Route::get('/profile/avatar/original', [ProfileController::class, 'avatarOriginal'])->name('profile.avatar.original');
});

// Profile photos live on the private disk; the strict pattern keeps paths inside it.
// Visitors who are not signed in only get the photos of public profiles.
Route::get('/avatars/{file}', AvatarController::class)
    ->where('file', '[0-9]+-[A-Za-z0-9]{24}\.webp')
    ->name('avatars.show');

/*
|--------------------------------------------------------------------------
| Admin console
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggleActive'])->name('users.toggle');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/trips', [AdminTripController::class, 'index'])->name('trips.index');
    Route::get('/trips/{trip}', [AdminTripController::class, 'show'])->name('trips.show');
    Route::post('/trips/{trip}/end', [AdminTripController::class, 'end'])->name('trips.end');
    Route::delete('/trips/{trip}', [AdminTripController::class, 'destroy'])->name('trips.destroy');

    Route::get('/donations', [AdminDonationController::class, 'index'])->name('donations.index');

    Route::get('/sponsors', [AdminSponsorController::class, 'index'])->name('sponsors.index');
    Route::post('/sponsors', [AdminSponsorController::class, 'store'])->name('sponsors.store');
    Route::get('/sponsors/{sponsor}/edit', [AdminSponsorController::class, 'edit'])->name('sponsors.edit');
    Route::put('/sponsors/{sponsor}', [AdminSponsorController::class, 'update'])->name('sponsors.update');
    Route::post('/sponsors/{sponsor}/toggle', [AdminSponsorController::class, 'toggle'])->name('sponsors.toggle');
    Route::post('/sponsors/{sponsor}/move', [AdminSponsorController::class, 'move'])->name('sponsors.move');
    Route::delete('/sponsors/{sponsor}', [AdminSponsorController::class, 'destroy'])->name('sponsors.destroy');

    Route::get('/sponsor-requests', [AdminSponsorInquiryController::class, 'index'])->name('sponsor-inquiries.index');
    Route::get('/sponsor-requests/{inquiry}', [AdminSponsorInquiryController::class, 'show'])->name('sponsor-inquiries.show');
    Route::put('/sponsor-requests/{inquiry}', [AdminSponsorInquiryController::class, 'update'])->name('sponsor-inquiries.update');
    Route::delete('/sponsor-requests/{inquiry}', [AdminSponsorInquiryController::class, 'destroy'])->name('sponsor-inquiries.destroy');

    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile');

    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings');
    Route::post('/settings/logo', [AdminSettingsController::class, 'updateLogo'])->name('settings.logo.update');
    Route::delete('/settings/logo', [AdminSettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
    Route::post('/settings/favicon', [AdminSettingsController::class, 'updateFavicon'])->name('settings.favicon.update');
    Route::delete('/settings/favicon', [AdminSettingsController::class, 'destroyFavicon'])->name('settings.favicon.destroy');

    Route::get('/maintenance', [AdminMaintenanceController::class, 'edit'])->name('maintenance');
    Route::put('/maintenance', [AdminMaintenanceController::class, 'update'])->name('maintenance.update');
    Route::get('/maintenance/preview', [AdminMaintenanceController::class, 'preview'])->name('maintenance.preview');
});

/*
|--------------------------------------------------------------------------
| Not found
| Unknown addresses go through the web middleware like any page, so the 404
| knows who is signed in and maintenance mode still applies. Keep this last.
|--------------------------------------------------------------------------
*/
Route::fallback(fn () => abort(404));
