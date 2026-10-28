<?php

$baseUrl = rtrim((string) env('BKASH_BASE_URL', 'https://checkout.pay.bka.sh/v1.2.0-beta'), '/');
$sandbox = filter_var(env('BKASH_SANDBOX', false), FILTER_VALIDATE_BOOL);

/*
|--------------------------------------------------------------------------
| Which bKash API generation to call
|--------------------------------------------------------------------------
| bKash ships two Checkout (PGW) API generations and merchant accounts are on
| one or the other. They differ in their paths:
|
|   legacy    POST {base}/checkout/token/grant
|             POST {base}/checkout/payment/create
|             POST {base}/checkout/payment/execute/{paymentID}
|             GET  {base}/checkout/payment/query/{paymentID}
|
|   tokenized POST {base}/tokenized/checkout/token/grant
|             POST {base}/tokenized/checkout/create
|             POST {base}/tokenized/checkout/execute         {"paymentID": ...}
|             POST {base}/tokenized/checkout/payment/status  {"paymentID": ...}
|
| Either way a paid payment answers transactionStatus "Completed".
|
| The host gives it away: `checkout.pay.bka.sh` and `checkout.sandbox.bka.sh`
| serve the legacy (non tokenized) API, while `tokenized.pay.bka.sh` and
| `tokenized.sandbox.bka.sh` serve the newer one. Set BKASH_API explicitly only
| if auto detection guesses wrong - `php artisan bkash:check` probes both.
*/
$api = \App\Services\BkashService::detectGeneration(env('BKASH_BASE_URL'), env('BKASH_API', 'auto'));

$legacy = $api === 'legacy';

return [

    /*
    |--------------------------------------------------------------------------
    | bKash Checkout (PGW) credentials
    |--------------------------------------------------------------------------
    | Values come from the bKash merchant portal. Leave them empty to hide the
    | bKash payment option in the app, everything else keeps working.
    */
    'base_url' => $baseUrl,

    /** 'legacy' or 'tokenized' - see the note above. */
    'api' => $api,

    /*
     * How the traveller reaches the hosted payment page:
     *
     *   browser   the legacy ``Checkout js`` in BKASH_SCRIPT_URL renders the page
     *             in an overlay. The page posts the amount and the payment id to
     *             Sofor, so no checkout URL is needed.
     *   redirect  the tokenized API returns `bkashURL` with the payment and the
     *             traveller is redirected to it.
     */
    'mode' => env('BKASH_MODE', $legacy ? 'browser' : 'redirect'),

    /** The path prefix is derived from the generation but can be overridden. */
    'path_prefix' => env('BKASH_PATH_PREFIX', $legacy ? '/checkout' : '/tokenized/checkout'),

    'paths' => \App\Services\BkashService::pathsFor($api),

    /** Execute and query take the payment id in the path on the legacy API. */
    'id_in_path' => $legacy,

    /*
     * The hosted payment page. The tokenized API returns it as `bkashURL` with
     * every payment; the legacy API does not, so a legacy account pastes the
     * Checkout URL shown in the bKash merchant portal here and Sofor appends
     * ?paymentID=... to it.
     */
    'checkout_url' => env('BKASH_CHECKOUT_URL'),

    'script_url' => env('BKASH_SCRIPT_URL', 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js'),

    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    | Sofor adds the mobile financial service (MFS) charge that bKash applies on
    | top of the traveller's payment, so the amount that lands in the ledger
    | stays exactly what was owed.
    */
    'currency' => 'BDT',

    'mfs_charge_percent' => (float) env('BKASH_MFS_CHARGE_PERCENT', 1.5),

    'timeout' => (int) env('BKASH_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Sandbox helpers
    |--------------------------------------------------------------------------
    | The sandbox only settles payments in whole taka, so amounts are rounded up
    | to the nearest taka when sandbox mode is on. Live accounts settle poisha.
    */
    'sandbox' => $sandbox,

    'intent_ttl_minutes' => (int) env('BKASH_INTENT_TTL', 120),

];
