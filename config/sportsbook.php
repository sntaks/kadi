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
        // Competitions bought from their own region (same credits): UK books hardly price Australia's NBL (probe
        // 2026-11-14: 2-3 books in uk, 6-11 in au). A market's own bookmakers (below) still come first.
        'competition_regions' => [
            'basketball_nbl' => 'au',
        ],
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
        'sport_groups' => [
            'Soccer' => 'Football',
            'Basketball' => 'Basketball',
            'Ice Hockey' => 'Ice Hockey',
            'Tennis' => 'Tennis',
            'Cricket' => 'Cricket',
            'Rugby League' => 'Rugby',
            'Rugby Union' => 'Rugby',
            'Boxing' => 'Boxing',
            'Mixed Martial Arts' => 'MMA',
        ],
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
            // Plan §10.3 (phase 3). Seasonal ones are dormant until the feed lists them: syncing them costs nothing
            // until a match is due.
            'soccer_uefa_europa_league',
            'soccer_uefa_europa_conference_league',
            'soccer_uefa_nations_league',
            'soccer_efl_champ',
            'soccer_netherlands_eredivisie',
            'soccer_portugal_primeira_liga',
            'soccer_spl',
            'soccer_turkey_super_league',
            'soccer_brazil_campeonato',
            'soccer_usa_mls',
            'soccer_england_efl_cup',
            'soccer_fa_cup',
            'soccer_germany_dfb_pokal',
            'soccer_spain_copa_del_rey',
            'soccer_fifa_world_cup',
            'soccer_fifa_world_cup_qualifiers_europe',
            'soccer_fifa_world_cup_qualifiers_south_america',
            'soccer_africa_cup_of_nations',
            'soccer_uefa_european_championship',
            'soccer_uefa_euro_qualification',
            'basketball_nbl',
            // Phase 4a: ice hockey.
            'icehockey_nhl',
            'icehockey_sweden_hockey_league',
            'icehockey_liiga',
            'icehockey_ahl',
            // Plan 20261120. Tennis keys are listed by the feed about a week before each tournament; until then they
            // are dormant and cost nothing.
            'tennis_atp_aus_open_singles',
            'tennis_wta_aus_open_singles',
            'tennis_atp_french_open',
            'tennis_wta_french_open',
            'tennis_atp_wimbledon',
            'tennis_wta_wimbledon',
            'tennis_atp_us_open',
            'tennis_wta_us_open',
            'tennis_atp_indian_wells',
            'tennis_wta_indian_wells',
            'tennis_atp_miami_open',
            'tennis_wta_miami_open',
            'tennis_atp_monte_carlo_masters',
            'tennis_atp_madrid_open',
            'tennis_wta_madrid_open',
            'tennis_atp_italian_open',
            'tennis_wta_italian_open',
            'tennis_atp_canadian_open',
            'tennis_wta_canadian_open',
            'tennis_atp_cincinnati_open',
            'tennis_wta_cincinnati_open',
            'tennis_atp_shanghai_masters',
            'tennis_atp_paris_masters',
            'tennis_atp_finals',
            'tennis_wta_finals',
            'cricket_test_match',
            'cricket_odi',
            'cricket_international_t20',
            'cricket_big_bash',
            'cricket_ipl',
            'cricket_t20_world_cup',
            'rugbyleague_nrl',
            'rugbyunion_six_nations',
            'boxing_boxing',
            'mma_mixed_martial_arts',
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
    | In play (plan §3–§4, §10.3)
    |--------------------------------------------------------------------------
    |
    | Live prices for the bulk feed markets of matches in play, from
    | `sportsbook:sync-live` (every 20 s, ticking every `tick_seconds`), only
    | while PLATFORM_SPORTS_IN_PLAY is on. Scores first, then odds: one credit
    | per market per competition in play, plus one for its scores, counted
    | against the `live` allowance. There is no match clock: the feed gives the
    | score only, so the late phase is estimated from kick-off.
    |
    */

    'in_play' => [
        'competitions' => array_values(array_filter(array_map('trim', explode(',', (string) env('SPORTSBOOK_IN_PLAY_COMPETITIONS',
            'soccer_epl,soccer_spain_la_liga,soccer_italy_serie_a,soccer_germany_bundesliga,soccer_france_ligue_one,soccer_uefa_champs_league,basketball_euroleague'))))),
        // Feed markets priced in play, per sport (main lines only; derived and extra markets stay closed).
        'markets' => [
            'football' => 'h2h,totals,spreads',
            'basketball' => 'h2h,totals',
        ],
        // The provider refreshes in-play prices every 40 s: a faster tick buys nothing.
        'tick_seconds' => (int) env('SPORTSBOOK_IN_PLAY_TICK_SECONDS', 40),
        // A live price older than this is suspended.
        'live_stale_seconds' => (int) env('SPORTSBOOK_IN_PLAY_STALE_SECONDS', 90),
        // After a score change, a market reopens only on prices fetched at least this long after the change (the
        // feed's prices lag the goal), never on the same tick.
        'score_change_hold_seconds' => (int) env('SPORTSBOOK_IN_PLAY_SCORE_HOLD_SECONDS', 40),
        // Higher than pre-match (0.06): the feed's live prices lag the bookmakers'.
        'margin' => (float) env('SPORTSBOOK_IN_PLAY_MARGIN', 0.08),
        // Minutes after kick-off when live betting closes, per sport. Football: 85'. EuroLeague: about the last two
        // minutes (40 playing minutes take about 1 h 45 with breaks).
        'close_after_minutes' => [
            'football' => 85,
            'basketball' => 100,
        ],
        // Phase 5b (plan §4.4–§4.5, Q4 decided 2026-11-14). A slip with any live leg waits this long (the longest of
        // its sports), is checked again (market open, price fresh and acceptable, score unchanged), then sent.
        'bet_delay_seconds' => [
            'football' => (int) env('SPORTSBOOK_IN_PLAY_DELAY_FOOTBALL', 8),
            'basketball' => (int) env('SPORTSBOOK_IN_PLAY_DELAY_BASKETBALL', 5),
        ],
        // Limits for a bet with any live leg, in shillings. Never above the Kadi API `sportsbook_live` product's
        // max_stake / max_payout: the API refuses a payout over its limit at settlement.
        'max_stake' => (int) env('SPORTSBOOK_IN_PLAY_MAX_STAKE', 5_000),
        'max_payout' => (int) env('SPORTSBOOK_IN_PLAY_MAX_PAYOUT', 250_000),
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
            'totals_label' => 'Total points',
            'popular' => ['basketball_nba', 'basketball_euroleague'],
        ],
        // Plan §10.3 (phase 4a). The feed's h2h is two-way and includes overtime and the shootout, as do its totals;
        // results come from the scores feed's final score (verified on 19 NHL games, 2026-11-14).
        'ice-hockey' => [
            'name' => 'Ice Hockey',
            'icon' => 'ice-hockey',
            'sort_order' => 3,
            'key_prefix' => 'icehockey_',
            'has_draw' => false,
            'derived_markets' => false,
            'markets' => env('ODDS_API_ICEHOCKEY_MARKETS', 'h2h,totals'),
            'max_score' => 30,
            'totals_label' => 'Total goals',
            'popular' => ['icehockey_nhl'],
        ],
        // Plan 20261120 (phases 4b-4e). None had a finished result in the scores feed's 3-day window when added, so each
        // starts settled by hand (`auto_settle: false`: staff record results in GMS). Switch a competition to automatic
        // in GMS once one of its feed results has been checked. Results are recorded as scores (docs/sportsbook-sports.md).
        'tennis' => [
            'name' => 'Tennis',
            'icon' => 'tennis',
            'sort_order' => 4,
            'key_prefix' => 'tennis_',
            'has_draw' => false,
            'derived_markets' => false,
            // Winner only: a games total would need the games count, which staff would have to record by hand.
            'markets' => env('ODDS_API_TENNIS_MARKETS', 'h2h'),
            // Sets won by each player (best of five: 3). A retirement after a completed set: the player who goes
            // through is recorded ahead; before that, the match is void.
            'max_score' => 3,
            'auto_settle' => false,
            'popular' => ['tennis_atp_aus_open_singles', 'tennis_wta_aus_open_singles', 'tennis_atp_french_open', 'tennis_wta_french_open', 'tennis_atp_wimbledon', 'tennis_wta_wimbledon', 'tennis_atp_us_open', 'tennis_wta_us_open'],
        ],
        'cricket' => [
            'name' => 'Cricket',
            'icon' => 'cricket',
            'sort_order' => 5,
            'key_prefix' => 'cricket_',
            'has_draw' => false,
            // Tests are priced with a Draw; limited-overs matches are two-way (a tie voids, unless a super over decides it).
            'draw_competitions' => ['cricket_test_match'],
            'derived_markets' => false,
            'markets' => env('ODDS_API_CRICKET_MARKETS', 'h2h'),
            // Winner recorded 1-0 or 0-1, a Test draw 0-0; a no result or abandoned match is void.
            'max_score' => 1,
            'auto_settle' => false,
            'popular' => ['cricket_test_match', 'cricket_odi', 'cricket_international_t20'],
        ],
        'rugby' => [
            'name' => 'Rugby',
            'icon' => 'rugby',
            'sort_order' => 6,
            // Both codes: rugbyleague_ (two-way, golden point decides a draw) and rugbyunion_ (draws happen).
            'key_prefix' => 'rugby',
            'has_draw' => false,
            'draw_competitions' => ['rugbyunion_*'],
            'derived_markets' => false,
            'markets' => env('ODDS_API_RUGBY_MARKETS', 'h2h,totals'),
            'max_score' => 150,
            'totals_label' => 'Total points',
            'auto_settle' => false,
            'popular' => ['rugbyleague_nrl', 'rugbyunion_six_nations'],
        ],
        'boxing' => [
            'name' => 'Boxing',
            'icon' => 'boxing',
            'sort_order' => 7,
            'key_prefix' => 'boxing_',
            // Most books price the draw; only those count. A draw wins Draw; a no contest is void.
            'has_draw' => true,
            'derived_markets' => false,
            'markets' => env('ODDS_API_BOXING_MARKETS', 'h2h'),
            // Winner recorded 1-0 or 0-1, a draw 0-0.
            'max_score' => 1,
            'auto_settle' => false,
            'popular' => ['boxing_boxing'],
        ],
        'mma' => [
            'name' => 'MMA',
            'icon' => 'mma',
            'sort_order' => 8,
            'key_prefix' => 'mma_',
            // Two-way: a draw recorded 0-0 voids both fighters; a no contest is void.
            'has_draw' => false,
            'derived_markets' => false,
            'markets' => env('ODDS_API_MMA_MARKETS', 'h2h'),
            'max_score' => 1,
            'auto_settle' => false,
            'popular' => ['mma_mixed_martial_arts'],
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
            // A bet with any in-play leg (plan §11.1): its own limits and its own line in finance reports.
            'live_bet' => 'sportsbook_live',
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

    /*
    |--------------------------------------------------------------------------
    | Countries and regions (sport page picker)
    |--------------------------------------------------------------------------
    |
    | The Odds API has no country field, so App\Sportsbook\Support\CompetitionRegion works one out: `competitions`
    | first (key => code), then the first word(s) of the key after the sport prefix matched against `countries`
    | (soccer_spain_la_liga → spain → es; the match is the name in snake case, or `match` when the key spells it
    | differently). Anything else is International: never hidden, never guessed from the name. A code with a file in
    | public/images/flags/{code}.svg shows that flag; regions without one (International, Africa, South America) show
    | a globe. `aliases` are extra words the picker's search matches (EPL, UCL...).
    |
    */

    'regions' => [
        // Shown before the countries, in this order.
        'regions' => [
            'world' => 'International',
            'eu' => 'Europe',
            'south-america' => 'South America',
            'africa' => 'Africa',
        ],
        'countries' => [
            'ar' => ['name' => 'Argentina'],
            'at' => ['name' => 'Austria'],
            'au' => ['name' => 'Australia'],
            'be' => ['name' => 'Belgium'],
            'br' => ['name' => 'Brazil'],
            'ch' => ['name' => 'Switzerland'],
            'cl' => ['name' => 'Chile'],
            'cn' => ['name' => 'China'],
            'de' => ['name' => 'Germany'],
            'dk' => ['name' => 'Denmark'],
            'es' => ['name' => 'Spain'],
            'fi' => ['name' => 'Finland'],
            'fr' => ['name' => 'France'],
            'gb-eng' => ['name' => 'England'],
            'gb-sct' => ['name' => 'Scotland'],
            'gr' => ['name' => 'Greece'],
            'ie' => ['name' => 'Ireland'],
            'in' => ['name' => 'India'],
            'it' => ['name' => 'Italy'],
            'jp' => ['name' => 'Japan'],
            'kr' => ['name' => 'South Korea', 'match' => 'korea'],
            'mx' => ['name' => 'Mexico'],
            'nl' => ['name' => 'Netherlands'],
            'no' => ['name' => 'Norway'],
            'pl' => ['name' => 'Poland'],
            'pt' => ['name' => 'Portugal'],
            'ru' => ['name' => 'Russia'],
            'sa' => ['name' => 'Saudi Arabia'],
            'se' => ['name' => 'Sweden'],
            'tr' => ['name' => 'Turkey'],
            'us' => ['name' => 'USA'],
        ],
        // Keys the rule above can't place (no country word, or the wrong one).
        'competitions' => [
            'soccer_epl' => 'gb-eng',
            'soccer_efl_champ' => 'gb-eng',
            'soccer_fa_cup' => 'gb-eng',
            'soccer_spl' => 'gb-sct',
            'soccer_league_of_ireland' => 'ie',
            'soccer_uefa_champs_league' => 'eu',
            'soccer_uefa_champs_league_women' => 'eu',
            'soccer_uefa_europa_league' => 'eu',
            'soccer_uefa_europa_conference_league' => 'eu',
            'soccer_uefa_nations_league' => 'eu',
            'soccer_uefa_european_championship' => 'eu',
            'soccer_uefa_euro_qualification' => 'eu',
            'soccer_fifa_world_cup_qualifiers_europe' => 'eu',
            'soccer_fifa_world_cup_qualifiers_south_america' => 'south-america',
            'soccer_conmebol_copa_libertadores' => 'south-america',
            'soccer_conmebol_copa_sudamericana' => 'south-america',
            'soccer_africa_cup_of_nations' => 'africa',
            'basketball_euroleague' => 'eu',
            'basketball_nba' => 'us',
            'basketball_ncaab' => 'us',
            'basketball_wncaab' => 'us',
            'basketball_nbl' => 'au',
            'icehockey_nhl' => 'us',
            'icehockey_ahl' => 'us',
            'icehockey_liiga' => 'fi',
            'icehockey_mestis' => 'fi',
            'cricket_big_bash' => 'au',
            'cricket_ipl' => 'in',
            'rugbyleague_nrl' => 'au',
            'rugbyunion_six_nations' => 'eu',
        ],
        'aliases' => [
            'soccer_epl' => 'premier league epl',
            'soccer_efl_champ' => 'championship efl',
            'soccer_uefa_champs_league' => 'ucl champions league',
            'soccer_uefa_europa_league' => 'uel',
            'soccer_uefa_europa_conference_league' => 'uecl conference',
            'soccer_spain_la_liga' => 'laliga',
            'soccer_germany_bundesliga' => 'buli',
            'soccer_africa_cup_of_nations' => 'afcon',
            'soccer_usa_mls' => 'major league soccer',
            'cricket_international_t20' => 't20',
            'cricket_big_bash' => 'bbl t20',
            'cricket_ipl' => 'indian premier league t20',
            'rugbyleague_nrl' => 'rugby league',
            'mma_mixed_martial_arts' => 'ufc mma',
        ],
    ],

];
