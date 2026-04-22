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

    'zomato' => [
        // Default points at the in-app stub (same origin). The stub speaks
        // Zomato v2.1 shape — see routes/web.php + ZomatoStubController.
        'base_url' => env('ZOMATO_BASE_URL', 'http://localhost:8000/zomato/api/v2.1'),
        'user_key' => env('ZOMATO_USER_KEY', 'stub'),
    ],

    'restaurants' => [
        // `zomato` → ZomatoProvider (real HTTP calls).
        // `fixture` → FixtureProvider (reads JSON directly; used in tests).
        'provider' => env('RESTAURANT_PROVIDER', 'zomato'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'bot_api_base' => env('TELEGRAM_BOT_API_BASE', 'https://api.telegram.org'),
    ],

];
