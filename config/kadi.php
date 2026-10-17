<?php

// SHA-256 hashes (hex) from a comma separated env var. Anything that is not exactly 64 hex characters
// is dropped, so a typo can never become a key that half-works.
$keyHashes = fn (string $name) => array_values(array_filter(array_map(
    fn (string $hash) => strtolower(trim($hash)),
    explode(',', (string) env($name, '')),
), fn (string $hash) => preg_match('/^[a-f0-9]{64}$/', $hash) === 1));

return [

    /*
    |--------------------------------------------------------------------------
    | Legal consent
    |--------------------------------------------------------------------------
    |
    | Users must confirm they are of legal age and accept the current terms.
    | We store only the confirmation (a timestamp) and the terms version they
    | accepted — never a date of birth. Bump `terms_version` whenever the
    | Terms or Privacy Policy change materially to re-prompt users at their
    | next login.
    |
    */

    'min_age' => 18,

    'terms_version' => '2026-09-24',

    /*
    | Operator details shown on /terms and /privacy (the documents leave these to the website).
    | Blank values are not shown, so set the address and licence number in .env before launch.
    */
    'legal' => [
        'operator' => env('KADI_LEGAL_OPERATOR', 'Kizuka Na Michezo Limited'),
        'website' => env('KADI_LEGAL_WEBSITE', 'https://kadi.online'),
        'registered_address' => env('KADI_LEGAL_ADDRESS'),
        'licence_number' => env('KADI_LICENCE_NUMBER'),
    ],

    /*
    | Customer care, shown in the footer, FAQ, legal pages and structured data. Read it through
    | App\Support\Contact, which formats the number for display and for tel: links.
    | `phone` is the local Kenyan form (0XXXXXXXXX).
    */
    'support' => [
        'phone' => env('KADI_SUPPORT_PHONE', '0182473265'),
        'email' => env('KADI_SUPPORT_EMAIL', 'kadiapponline@gmail.com'),
        'whatsapp' => env('KADI_SUPPORT_WHATSAPP', 'https://wa.me/kadi.OnlineKE'),
    ],

    /*
    | M-Pesa Pay Bill number players load their wallet with by hand (account no. = their account_no),
    | shown on the wallet, sportsbook, home and FAQ pages. Display only: STK push and the C2B API calls
    | use MPESA_C2B_SHORTCODE (config/mpesa.php). Blank hides it everywhere.
    */
    'paybill' => env('MPESA_SHORTCODE'),

    /*
    | Official social profiles: footer icons (public/socials/*) and Organization `sameAs`.
    | Order here is the order on the page. Remove an entry to hide it.
    */
    'socials' => [
        'facebook' => ['label' => 'Facebook', 'url' => 'https://www.facebook.com/profile.php?id=61579589735615', 'icon' => 'socials/facebook-icon.svg'],
        'instagram' => ['label' => 'Instagram', 'url' => 'https://www.instagram.com/kadi.online', 'icon' => 'socials/instagram-icon.svg'],
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@KadiKingsLtd', 'icon' => 'socials/youtube.svg'],
        'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/kadi.OnlineKE', 'icon' => 'socials/whatsapp-icon.svg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push
    |--------------------------------------------------------------------------
    |
    | Browsers hand us a push "endpoint" URL that the server later POSTs to. To
    | stop that being used to reach internal hosts, only endpoints on these push
    | services are accepted (`*` matches any prefix). Add a host here if a browser
    | vendor you need to support uses a different push service.
    |
    */

    'push_api' => [
        /*
         * SHA-256 hashes (hex) of the bearer keys the game server may use, comma separated in
         * PUSH_API_KEY_HASHES. Only hashes live in .env; generate a key and its hash with
         * `php artisan push-api:key`. List two hashes while rotating. Empty (or no valid entry)
         * means the API is disabled and every request is refused.
         */
        'key_hashes' => $keyHashes('PUSH_API_KEY_HASHES'),

        /*
         * Keys allowed to send a system-wide broadcast to EVERY registered device. Deliberately a
         * separate list from `key_hashes`: the per-player key must not be able to notify everyone.
         * Same format and rotation rules. Empty disables broadcasts.
         */
        'broadcast_key_hashes' => $keyHashes('PUSH_API_BROADCAST_KEY_HASHES'),

        // Optional extra layer: only these caller IPs may use the API (comma separated PUSH_API_ALLOWED_IPS).
        // Empty means any IP with a valid key. Behind a proxy/CDN, configure trusted proxies first.
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('PUSH_API_ALLOWED_IPS', ''))))),

        'max_recipients' => 100,                                                       // per request
        'requests_per_minute' => (int) env('PUSH_API_REQUESTS_PER_MINUTE', 600),       // per key
        'failed_attempts_per_minute' => 20,                                            // bad keys, per caller IP
        'per_user_per_hour' => (int) env('PUSH_API_PER_USER_PER_HOUR', 30),            // pushes one player may receive

        'default_ttl' => 900,                                                          // seconds a push may wait for an offline device
        'max_ttl' => 86400,
        // `high` wakes a sleeping phone: without it Android (Doze) and iOS hold `normal` pushes back until the
        // device is next active, so time-sensitive alerts and announcements can arrive late. Allowed by default;
        // set PUSH_API_ALLOW_HIGH_URGENCY=false to refuse it again. Senders still default to `normal`.
        'allowed_urgencies' => array_values(array_filter(
            ['very-low', 'low', 'normal', 'high'],
            fn (string $urgency) => $urgency !== 'high' || filter_var(env('PUSH_API_ALLOW_HIGH_URGENCY', true), FILTER_VALIDATE_BOOLEAN),
        )),

        // System-wide broadcasts (announcements only: maintenance, tournaments, new versions).
        'broadcast_default_ttl' => 3600,                                               // announcements may wait longer than a turn alert
        'broadcast_per_hour' => (int) env('PUSH_API_BROADCAST_PER_HOUR', 6),           // per broadcast key
        'broadcast_per_day' => (int) env('PUSH_API_BROADCAST_PER_DAY', 4),             // across all keys and the command
        'broadcast_chunk_size' => 200,                                                 // devices per queued job

        // Optional dedicated queue name for broadcast jobs (PUSH_API_BROADCAST_QUEUE), so a big send
        // cannot delay per-player pushes. null = the default queue. A worker MUST listen on the name
        // (`queue:work redis --queue=broadcasts`) or broadcasts are never sent. Anything that is not a
        // plain queue name is ignored.
        'broadcast_queue' => preg_match('/^[A-Za-z0-9_\-]{1,64}$/', (string) env('PUSH_API_BROADCAST_QUEUE')) === 1 ? (string) env('PUSH_API_BROADCAST_QUEUE') : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet webhook (KadiApi -> this site)
    |--------------------------------------------------------------------------
    |
    | KadiApi signs every wallet-balance-changed webhook with one active secret.
    | This side accepts a LIST so both an old and a new secret work during a
    | rotation window. Raw secrets, not hashes: HMAC verification needs the
    | actual shared value, unlike the push API's bearer keys.
    |
    */

    'wallet_webhook' => [
        'secrets' => array_values(array_filter(array_map('trim', explode(',', (string) env('KADI_WALLET_WEBHOOK_SECRETS', ''))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Game history and disputes
    |--------------------------------------------------------------------------
    |
    | Players see their latest games, tournaments and jackpots (read from KadiApi) and may dispute
    | one. See docs/game-disputes.md. KadiApi's complaints endpoint allows only 30 calls a minute
    | for the WHOLE site, so each player is capped here well below that.
    |
    */

    'game_disputes' => [
        // KadiApi path (under API_URL) for a player's latest 10 of each kind; the encrypted customer id is appended.
        'played_endpoint' => trim((string) env('KADI_PLAYED_GAMES_ENDPOINT', 'customers/played/recent'), '/'),

        'games_per_list' => 10,
        // Short, because the report window is only minutes long: a new game must show up quickly.
        'cache_seconds' => 15,

        // The game server's kadi.game_level_pending.expires_at is the real deadline. This is the window
        // shown in the page text, and the fallback (from KadiApi's created_at) if the game DB is down.
        'report_window_minutes' => (int) env('KADI_DISPUTE_WINDOW_MINUTES', 3),

        // Reports one player may send to KadiApi (accepted or not) per window.
        'max_attempts' => 3,
        'decay_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone verification (SMS code)
    |--------------------------------------------------------------------------
    |
    | Players confirm their phone with a 6-digit code sent through TextSMS before deposits,
    | withdrawals and referral payouts. Every send costs money, so sends are capped per player,
    | per number and per IP. See docs/phone-verification.md.
    |
    */

    'phone_otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,          // wrong codes before the code is burned
        'resend_seconds' => 60,
        'sends_per_hour_user' => 5,
        'sends_per_hour_phone' => 5,
        'sends_per_hour_ip' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Referrals ("Invite & Earn")
    |--------------------------------------------------------------------------
    |
    | KadiApi owns the referral ledger, bonuses and payouts; this site makes each player's code,
    | link and QR code, carries ?ref= codes to POST customers and shows the referral page.
    | See docs/referrals.md.
    |
    */

    'referrals' => [
        'enabled' => (bool) env('KADI_REFERRALS_ENABLED', true),
        'cookie' => 'kadi_ref',
        'cookie_days' => 30,
        'code_length' => 8,
        'code_attempts' => 5,          // 409 "taken" retries before giving up
        'minimum_withdrawal' => 50,    // fallback when the wallet response has none
        'lookup_cache_minutes' => 10,
        'page_cache_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotions (signup bonus)
    |--------------------------------------------------------------------------
    |
    | KadiApi decides who qualifies and pays the bonus; this site collects the promo code, reports
    | verification and shows the bonus and what is still locked. `enabled` only hides the promo
    | field, ?promo= links and the promo notices: the locked-bonus note and withdraw cap always
    | follow KadiApi. See docs/promotions.md.
    |
    */

    'promotions' => [
        'enabled' => (bool) env('KADI_PROMOTIONS_ENABLED', true),
        'cookie' => 'kadi_promo',
        'cookie_days' => 30,
        'signup_bonus_amount' => 20,       // copy only; KadiApi sets the real amount
        'lookup_cache_seconds' => 60,      // a code can be used up, so keep this short
        'lookups_per_minute' => 10,        // per IP, on the sign-up screen
        'cache_seconds' => 60,             // GET customers/{id}/promotions on the wallet page
        // Jackpot wallet the game server creates for a player whose promo code earned the bonus
        // (mpesa_create_jp_wallet.php, after POST customers/{id}/verified). Sent once, never retried.
        'jackpot_wallet' => [
            'type' => env('KADI_PROMO_JACKPOT_TYPE', 'BRONZE'),
            'amount' => (int) env('KADI_PROMO_JACKPOT_AMOUNT', 20),
        ],
    ],

    'push' => [
        // Environments where any signed-in user may use the "Send test notification" button
        // (admins may use it everywhere). See App\Services\PushTestSender.
        'test_environments' => ['local', 'staging'],

        'allowed_endpoint_hosts' => [
            'fcm.googleapis.com',            // Chrome, Edge, Brave, Opera, Samsung Internet (Android)
            '*.push.services.mozilla.com',   // Firefox
            '*.notify.windows.com',          // Edge on Windows (WNS)
            '*.push.apple.com',              // Safari / iOS / iPadOS home-screen apps
        ],
    ],

];
