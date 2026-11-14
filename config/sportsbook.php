<?php

/*
|--------------------------------------------------------------------------
| The merged sportsbook (plan §5, Phase 7)
|--------------------------------------------------------------------------
|
| Ported from the standalone sportsbook. Its wallet, phone-code, mobile API,
| reports and exports settings are not here: Kadi's wallet (the Kadi API),
| accounts and back office replace them. Everything runs only while
| PLATFORM_SPORTS is on (config/platform.php).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Brand & Compliance
    |--------------------------------------------------------------------------
    |
    | The brand name itself comes from APP_NAME. The licence line is shown in
    | the site footer and must match the operator's BCLB licence exactly
    | before launch; leave it empty until a licence is held.
    |
    */

    'licence_text' => env('SPORTSBOOK_LICENCE_TEXT'),

    'minimum_age' => 18,

    'timezone' => 'Africa/Nairobi',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Money is stored as integers in the currency's minor unit (cents), never
    | as floats. Limits below are expressed in whole shillings.
    |
    */

    'currency' => [
        'code' => 'KES',
        'minor_units' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Betting Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'min_stake' => (int) env('SPORTSBOOK_MIN_STAKE', 10),
        'max_stake' => (int) env('SPORTSBOOK_MAX_STAKE', 20_000),
        'max_payout' => (int) env('SPORTSBOOK_MAX_PAYOUT', 1_000_000),
        'max_selections' => (int) env('SPORTSBOOK_MAX_SELECTIONS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | Margin is applied on top of the de-margined consensus price. Model-priced
    | markets (derived from 1X2 / totals) carry an additional margin because
    | the risk is entirely ours.
    |
    */

    'odds' => [
        'margin' => (float) env('SPORTSBOOK_ODDS_MARGIN', 0.06),
        'derived_market_margin' => (float) env('SPORTSBOOK_DERIVED_MARGIN', 0.10),
        'minimum_bookmakers' => (int) env('SPORTSBOOK_MINIMUM_BOOKMAKERS', 3),
        // Expected goals per match when a competition has no feed Over 2.5 prices to learn from. Recorded top-league
        // prices implied about 2.75.
        'default_total_goals' => (float) env('SPORTSBOOK_DEFAULT_TOTAL_GOALS', 2.7),
        // Share of each team's goals expected in the first half, for half-time markets. UNVERIFIED rule of thumb.
        'first_half_goal_share' => (float) env('SPORTSBOOK_FIRST_HALF_GOAL_SHARE', 0.45),
        // Model-priced markets (e.g. correct score) never offer more than this.
        'derived_maximum_price' => (float) env('SPORTSBOOK_DERIVED_MAXIMUM_PRICE', 500),
        'stale_after_hours' => (int) env('SPORTSBOOK_ODDS_STALE_AFTER_HOURS', 26),
    ],

    /*
    |--------------------------------------------------------------------------
    | The Odds API Budget
    |--------------------------------------------------------------------------
    |
    | Credentials live in config/services.php. These settings keep usage
    | inside the plan's monthly quota: calls are refused once the remaining
    | credits would fall below the reserve.
    |
    */

    'odds_api' => [
        // "live" calls the API; "replay" serves recordings and never spends credits.
        'driver' => env('ODDS_API_DRIVER', 'replay'),
        'recordings_path' => database_path('sportsbook/odds-api-recordings'),
        'region' => env('ODDS_API_REGION', 'uk'),
        // Comma-separated bookmaker keys (e.g. pinnacle,betfair_ex_uk,williamhill). When set they replace `region`:
        // each group of 10 costs one region's credits. Leave empty to use the region. The replay driver ignores it.
        'bookmakers' => env('ODDS_API_BOOKMAKERS', ''),
        // Markets bought from their own bookmakers, in a call of their own (same credits per market). The UK region
        // hardly prices football handicaps; these nine books do (EPL probe 2026-11-14: 12 of 20 matches had a whole or
        // half line priced by 3 or more of them).
        'market_bookmakers' => [
            'spreads' => env('ODDS_API_SPREADS_BOOKMAKERS', 'pinnacle,onexbet,marathonbet,mybookieag,bovada,coolbet,betonlineag,lowvig,betus'),
        ],
        // Comma-separated market keys pulled by the scheduled odds sync.
        'markets' => env('ODDS_API_MARKETS', 'h2h'),
        // Odds are only bought for competitions with a fixture kicking off within this many days.
        'lookahead_days' => (int) env('ODDS_API_LOOKAHEAD_DAYS', 7),
        // Feed sport groups imported as sports, mapped to the name players see.
        'sport_groups' => ['Soccer' => 'Football', 'Basketball' => 'Basketball'],
        // Competitions synced out of the box (CompetitionSeeder) and recorded for replay.
        'default_competitions' => [
            'soccer_epl',
            'soccer_spain_la_liga',
            'soccer_italy_serie_a',
            'soccer_germany_bundesliga',
            'soccer_france_ligue_one',
            'soccer_uefa_champs_league',
            'basketball_nba',
            'basketball_euroleague',
        ],
        'odds_format' => 'decimal',
        'monthly_credit_cap' => (int) env('ODDS_API_MONTHLY_CREDIT_CAP', 100000),
        'credit_reserve' => (int) env('ODDS_API_CREDIT_RESERVE', 2000),
        // Credits a month per purpose (App\Sportsbook\OddsApi\OddsApiPurpose), on top of the cap: a purpose that
        // reaches its allowance stops until next month, so in play can never starve pre-match prices or settlement.
        // What is left under the cap (10,000 by default) is headroom, which recordings spend. Plan §10.2.
        'allowances' => [
            'pre_match' => (int) env('ODDS_API_ALLOWANCE_PRE_MATCH', 9000),
            'per_event' => (int) env('ODDS_API_ALLOWANCE_PER_EVENT', 6000),
            'live' => (int) env('ODDS_API_ALLOWANCE_LIVE', 72000),
            'scores' => (int) env('ODDS_API_ALLOWANCE_SCORES', 3000),
        ],
        // platform:health warns once the month's spend reaches this share of the cap or of any allowance.
        'warn_at' => (float) env('ODDS_API_WARN_AT', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-match extras (plan §10.6)
    |--------------------------------------------------------------------------
    |
    | Alternate handicaps, alternate goal lines and team goal lines, bought per match from the event endpoint
    | (`sportsbook:sync-event-odds`, hourly) once in each window before kick-off. One credit per market returned,
    | from up to 10 named books. Spend counts against the `per_event` allowance.
    |
    */

    'per_event' => [
        'enabled' => (bool) env('SPORTSBOOK_PER_EVENT_ENABLED', true),
        'competitions' => array_values(array_filter(array_map('trim', explode(',', (string) env('SPORTSBOOK_PER_EVENT_COMPETITIONS',
            'soccer_epl,soccer_spain_la_liga,soccer_italy_serie_a,soccer_germany_bundesliga,soccer_france_ligue_one,soccer_uefa_champs_league'))))),
        'markets' => array_values(array_filter(array_map('trim', explode(',', (string) env('SPORTSBOOK_PER_EVENT_MARKETS',
            'alternate_spreads,alternate_totals,alternate_team_totals'))))),
        'bookmakers' => env('ODDS_API_PER_EVENT_BOOKMAKERS', 'pinnacle,onexbet,bovada,fanduel,draftkings,betmgm,betrivers,fanatics,coolbet,betfair_ex_uk'),
        // Hours before kick-off: a match is bought once while its kick-off is this far away (the day before, and
        // shortly before kick-off).
        'windows' => [[20, 28], [1, 3]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sports
    |--------------------------------------------------------------------------
    |
    | How each sport is priced, settled and shown (App\Sportsbook\Support\SportProfile), keyed by the
    | sb_sports slug. `key_prefix` ties feed competitions (`basketball_nba`) to their sport. A sport without a
    | draw has a two-way Winner (incl. overtime); only football derives markets (BTTS, correct score...) from
    | the 1X2. `markets` are bought from the feed: every market costs one credit per competition per sync, and
    | the plan has 100,000 credits a month (docs/sportsbook-sports.md has the budget).
    |
    */

    'sports' => [
        'football' => [
            'name' => 'Football',
            'icon' => 'football',
            'sort_order' => 1,
            'key_prefix' => 'soccer_',
            'has_draw' => true,
            'derived_markets' => true,
            'markets' => env('ODDS_API_MARKETS', 'h2h,totals,spreads'),
            // The highest score staff may record (back-office API).
            'max_score' => 99,
            'popular' => ['soccer_epl', 'soccer_uefa_champs_league', 'soccer_spain_la_liga'],
        ],
        'basketball' => [
            'name' => 'Basketball',
            'icon' => 'basketball',
            'sort_order' => 2,
            'key_prefix' => 'basketball_',
            'has_draw' => false,
            'derived_markets' => false,
            'markets' => env('ODDS_API_BASKETBALL_MARKETS', 'h2h,totals'),
            'max_score' => 300,
            'popular' => ['basketball_nba', 'basketball_euroleague'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | Results come from the paid scores feed (2 credits a call), fetched only
    | for competitions with open bets on matches that should have finished,
    | and at most once per competition per check interval (decision 33).
    | Competitions listed as manual are settled from the admin panel.
    |
    */

    'settlement' => [
        'result_after_minutes' => 135,
        'scores_check_interval_hours' => 12,
        'scores_days_from' => 3,
        // A leg is void when its match moves more than this from the kick-off it was bet on.
        'postponement_void_hours' => 48,
        // Settled by hand until it is known whether the feed's scores include extra time.
        'manual_competitions' => ['soccer_uefa_champs_league'],
        // Winnings tax is withheld by the Kadi API (each product's winnings_tax_rate), never here.
    ],

    /*
    |--------------------------------------------------------------------------
    | Kadi API wagers
    |--------------------------------------------------------------------------
    |
    | Bets and jackpot entries are wagers in the one Kadi wallet (plan §5.3).
    | The game key is services.kadi_api.sportsbook_game_key (.env only). The
    | outbox sends settlements and reversals in order per wager; a call that
    | fails for a reason that can pass (timeout, 5xx, 429, a version that is
    | not current yet) is tried again with backoff, then left for an operator.
    |
    */

    'wagers' => [
        'game' => 'sportsbook',
        'products' => [
            'bet' => 'sportsbook',
            'jackpot_entry' => 'sportsbook_jackpot',
        ],
        'timeout_seconds' => (int) env('SPORTSBOOK_WAGER_TIMEOUT', 10),
        // Queue for the outbox and reconcile jobs. The scheduler also sends due ops every minute without a worker.
        'queue' => env('SPORTSBOOK_WAGER_QUEUE', 'default'),
        // Backoff in seconds between attempts at one op; after the last, it waits for an operator.
        'op_backoff' => [30, 60, 120, 300, 600, 1800, 3600],
        'op_max_attempts' => (int) env('SPORTSBOOK_WAGER_OP_MAX_ATTEMPTS', 12),
        // A placement still unknown after this long is listed for an operator.
        'pending_alert_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Jackpot
    |--------------------------------------------------------------------------
    |
    | Defaults for a new jackpot; an admin can change each one per jackpot
    | while it is a draft. Amounts are whole shillings. Each tier's pool is
    | split equally between the entries with exactly that many right; more
    | void matches than void_limit cancels the jackpot and refunds everyone.
    |
    */

    'jackpot' => [
        'entry_price' => 99,
        // Matches right => prize pool.
        'prize_tiers' => [17 => 1_000_000, 16 => 100_000, 15 => 20_000],
        'void_limit' => 3,
        'min_matches' => 13,
        'max_matches' => 17,
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotions
    |--------------------------------------------------------------------------
    |
    | Free bets (decision 41): defaults for a new one, which an admin can
    | change when giving it, and how long a replacement lasts when the bet a
    | free bet was used on ends void or is cancelled.
    |
    | Multibet bonus (decision 42): a percentage of a winning multi's profit
    | (payout minus stake), by how many winning legs are priced at least
    | min_price; a leg count between tiers takes the tier below. Never on
    | free bets, and never past the maximum payout.
    |
    */

    'promotions' => [
        'free_bets' => [
            'min_odds' => '1.50',
            'expiry_days' => 7,
            'replacement_days' => 7,
        ],
        'multibet_bonus' => [
            'min_price' => '1.30',
            // Qualifying legs => percent.
            'tiers' => [3 => 3, 4 => 5, 5 => 7, 6 => 10, 8 => 15, 10 => 20, 15 => 35, 20 => 50],
        ],
    ],

];
