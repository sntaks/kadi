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

    'analytics' => [
        'ga_measurement_id' => env('GA_MEASUREMENT_ID'),
        'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    'kadi_api' => [
        'url' => env('API_URL'),
        'key' => env('API_KEY'),
        'image_url' => env('KADI_IMAGE_URL', 'https://gameapi.kadi.online/kadi/images'),
        'play_url' => env('KADI_PLAY_URL', 'https://kadi.online'),
        // Game server PHP scripts (e.g. mpesa_create_jp_wallet.php for the promo jackpot wallet).
        'game_api_url' => env('KADI_GAME_API_URL', 'https://gameapi.kadi.online/kadi'),
    ],

    // TextSMS (sms.textsms.co.ke): phone verification codes. The API key lives in .env only.
    'textsms' => [
        'url' => env('TEXTSMS_URL', 'https://sms.textsms.co.ke/api/services/sendsms/'),
        'api_key' => env('TEXTSMS_API_KEY'),
        'partner_id' => env('TEXTSMS_PARTNER_ID'),
        'shortcode' => env('TEXTSMS_SHORTCODE', 'TextSMS'),
    ],

    'bugs_api' => [
        'url' => env('BUGS_API_URL'),
        'key' => env('BUGS_API_KEY'),
    ],

    'kadi' => [
        'image_upload_url' => env('KADI_IMAGE_UPLOAD_URL'),
        'image_upload_key' => env('KADI_IMAGE_UPLOAD_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL'),
        'sender_name' => env('BREVO_SENDER_NAME'),
        'endpoint' => env('BREVO_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
        'base_url' => env('UNSPLASH_BASE_URL', 'https://api.unsplash.com'),
        'source_url' => env('UNSPLASH_SOURCE_URL', 'https://images.unsplash.com'),
    ],

    // The Odds API (the merged sportsbook, config/sportsbook.php). The key lives in .env only; with
    // ODDS_API_DRIVER=replay (the default) no call is made and no credit is spent.
    'odds_api' => [
        'key' => env('ODDS_API_KEY'),
        'base_url' => env('ODDS_API_BASE_URL', 'https://api.the-odds-api.com/v4'),
    ],
];
