<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    | Text messages: phone verification and password reset codes.
    | "smsnetbd" sends through sms.net.bd, "log" only writes them to the log.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        // What a traveller's SMS wallet pays per SMS part, and the top-up limits (BDT).
        'price' => (float) env('SMS_PRICE', 0.45),
        'min_topup' => (int) env('SMS_MIN_TOPUP', 10),
        'max_topup' => (int) env('SMS_MAX_TOPUP', 5000),
        'smsnetbd' => [
            'url' => env('SMS_NET_BD_URL', 'https://api.sms.net.bd'),
            'key' => env('SMS_NET_BD_API_KEY'),
            'sender_id' => env('SMS_NET_BD_SENDER_ID'),
        ],
    ],

    /*
    | The Android app (see ANDROID_APP_PLAN.md).
    | - sha256: the app signing certificate fingerprints from Play Console
    |   (App integrity), comma separated, so invite and pay links open in the app.
    | - min_version_code: builds older than this are told to update. 0 = none.
    | - sms_hash: the 11 character SMS Retriever hash, so codes fill themselves in.
    */
    'android' => [
        'package' => env('ANDROID_APP_PACKAGE', 'io.sofor.app'),
        'sha256' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANDROID_APP_SHA256', ''))))),
        'min_version_code' => (int) env('ANDROID_MIN_VERSION_CODE', 0),
        'latest_version_code' => (int) env('ANDROID_LATEST_VERSION_CODE', 0),
        'sms_hash' => env('ANDROID_SMS_HASH'),
    ],

    /*
    | Push alerts through Firebase Cloud Messaging. `credentials` is the path
    | to the service account JSON file, kept outside the web root. Empty = no
    | pushes are sent.
    */
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    /*
    | Ownership checks for search engines: the "content" value of the meta tag
    | Google Search Console and Bing Webmaster Tools give you. Empty = no tag.
    */
    'search_console' => [
        'google' => env('GOOGLE_SITE_VERIFICATION'),
        'bing' => env('BING_SITE_VERIFICATION'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
