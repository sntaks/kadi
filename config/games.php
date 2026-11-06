<?php

/*
|--------------------------------------------------------------------------
| Game catalogue presentation
|--------------------------------------------------------------------------
|
| The Kadi API (`GET /games`) decides which games and products exist and are
| playable, their names and their launch URLs. This file only says how they
| look in the lobby: artwork, collections and badges. A product the API lists
| but this file does not know still shows, as a plain fallback tile; a product
| listed here but not by the API never shows.
|
| Read through App\Games\GameCatalogue. `php artisan games:list` shows the
| joined result, `php artisan games:artwork` builds the lobby thumbnails.
|
*/

return [

    'catalogue' => [
        // Fresh for 5 minutes; the last good copy is served for 24 hours if the API is down.
        'fresh_seconds' => (int) env('GAMES_CATALOGUE_FRESH_SECONDS', 300),
        'stale_seconds' => (int) env('GAMES_CATALOGUE_STALE_SECONDS', 86400),
        'timeout_seconds' => 5,

        // A game is only listed when its launch_url is https on one of these hosts (`*.` matches
        // subdomains), so a bad catalogue entry can never send players to another site.
        'launch_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('GAMES_LAUNCH_HOSTS', 'kadi.online,*.kadi.online'))))),
    ],

    /*
    | Thumbnails built by `games:artwork` from the originals under public/.
    | 320x480 WebP keeps the whole lobby around 4.5 MB instead of 66 MB.
    */
    'artwork' => [
        'thumbnails' => 'games/thumbs',
        'width' => 320,
        'height' => 480,
        'quality' => 72,
    ],

    /*
    | Per game. `titles` true: every product is its own lobby tile (slots,
    | scratch cards). false: the game is one tile and its products are modes
    | (Kadi singles, tournaments, jackpots). Product codes are the API's own
    | (`berry_blast`, `safari`), unique across games. `artwork` points a product
    | at its original under public/ (`{code}` is the product code);
    | `artwork_map` names an original per product instead.
    */
    'games' => [
        'kadi' => [
            'titles' => false,
            // Kadi keeps its own launch: a form POST of `ggid` (the player's account_no) to
            // services.kadi_api.play_url. Every other game uses a single-use API launch token.
            'launch' => 'ggid',
            // Played inside the site's frame like slots and scratch cards (/play/kadi): the ggid form is
            // posted into the frame, never off-site. The play page still offers "Open in its own tab" if
            // the game doesn't load. KADI_IN_FRAME=false sends Play Kadi to Kadi's own page instead.
            'frame' => (bool) env('KADI_IN_FRAME', true),
            'tagline' => 'Classic Kenyan Kadi: singles, tournaments and jackpots.',
            // Players seated right now per mode (App\Games\KadiTables). The only seat counts the site shows.
            'totals_url' => env('KADI_TOTALS_URL') ?: 'https://gameapi.kadi.online/kadi/get_user_totals.php',
            // The published jackpot payouts (KSh, per player in each place; `total` is the whole pool). The one
            // copy: the games guide's tables and the landing hero's prize pool both read it, so they never differ.
            'jackpots' => [
                'bronze' => ['name' => 'Bronze jackpot', 'entry' => 20, 'games' => 13, 'total' => 131189, 'prizes' => [
                    ['1st place', 86291], ['Runner-up', 19544], ['Semis (2)', 7795], ['Quarters (4)', 2441],
                ]],
                'silver' => ['name' => 'Silver jackpot', 'entry' => 50, 'games' => 17, 'total' => 5242871, 'prizes' => [
                    ['1st place', 3451655], ['Runner-up', 781760], ['Semis (2)', 311830], ['Quarters (4)', 96449],
                ]],
                'gold' => ['name' => 'Gold jackpot', 'entry' => 100, 'games' => 21, 'total' => 167772160, 'prizes' => [
                    ['1st place', 110452969], ['Runner-up', 25016339], ['Semis (2)', 9978584], ['Quarters (4)', 3086378],
                ]],
            ],
            // The jackpot whose prizes the landing hero's Kadi slide shows.
            'hero_jackpot' => 'gold',
        ],
        'slots' => [
            'titles' => true,
            'tagline' => 'Themed slots, one wallet.',
            'artwork' => 'games/slots_games/{code}.webp',
        ],
        'scratch' => [
            'titles' => true,
            'tagline' => 'Scratch the foil, see the prize.',
            'artwork_map' => [
                'fantasy' => 'games/scratch_games/fantasy.png',
                'fruit' => 'games/scratch_games/fruit.png',
                'leprechaun' => 'games/scratch_games/leprechaun.png',
                'safari' => 'games/scratch_games/safari.png',
            ],
        ],
    ],

    /*
    | Lobby collections, in display order. A slot belongs to one collection.
    */
    'collections' => [
        'legends' => [
            'label' => 'Dragons & legends',
            'products' => [
                'crown_of_embers',
                'dragon_fire_fortune',
                'dragon_realm',
                'dragons_coin_hoard',
                'fortune_fan',
                'golden_koi_fortune',
                'lucky_lanterns',
                'mystic_forest',
                'neon_city',
                'space_odyssey',
                'wizards_wild_reels',
            ],
        ],
        'pharaoh' => [
            'label' => "Pharaoh's vault",
            'products' => [
                'curse_of_the_pyramid',
                'desert_jewels',
                'egypt_gold',
                'eye_of_the_sun',
                'golden_idol_quest',
                'jungle_relic_hunter',
                'lost_temple',
                'nile_treasures',
                'pharaohs_fortune',
                'scarab_gold',
                'secrets_of_the_sunken_ruins',
                'tomb_raiders_riches',
                'torchlight_temple',
            ],
        ],
        'seas' => [
            'label' => 'High seas',
            'products' => [
                'captains_chest',
                'compass_of_fortune',
                'diamond_cascade_fortune',
                'explorers_gold_rush',
                'gold_rush_gems',
                'krakens_gold',
                'mermaid_millions',
                'ocean_treasure',
                'pearl_divers_delight',
                'pirate_gold',
                'royal_gems',
                'the_lost_map_legacy',
                'treasure_tide',
                'treasure_trail_expedition',
            ],
        ],
        'wild' => [
            'label' => 'Untamed',
            'products' => [
                'buffalo_thunder_gold',
                'canyon_quest_jackpot',
                'frozen_fortune',
                'jade_tiger_riches',
                'jungle_quest',
                'mountain_peak_treasure',
                'silver_stake_showdown',
                'tropical_paradise',
                'wild_safari',
                'wild_west_showdown',
            ],
        ],
        'dark' => [
            'label' => 'After dark',
            'products' => [
                'blood_moon_vampires',
                'count_draculas_treasure_vault',
                'crimson_fangs_fortune',
                'cursed_cauldron_jackpot',
                'gothic_curse_of_the_crypt',
                'halloween',
                'hexed_reels',
                'midnight_coven_riches',
                'midnight_moon_wilds',
                'werewolf_moon_wilds',
                'witch_hunters_bounty',
            ],
        ],
        'fruit' => [
            'label' => 'Fruit stand',
            'products' => [
                'berry_blast',
                'candy_kingdom',
                'cherry_bomb_deluxe',
                'coffee_delight',
                'farm_fortune',
                'fruits',
                'golden_bell_rush',
                'lucky_bar_blitz',
                'lucky_sevens',
                'sizzling_sevens',
                'triple_jackpot_fruits',
            ],
        ],
    ],

    /*
    | Badges. `jackpot` marks titles with a jackpot feature in the game itself;
    | `featured` is the lobby's hand-picked row (a curated pick, not a
    | popularity claim, so it is never labelled "most played").
    */
    'badges' => [
        'jackpot' => [
            'canyon_quest_jackpot',
            'cursed_cauldron_jackpot',
            'triple_jackpot_fruits',
        ],
    ],

    /*
    | Launching (App\Games\GameLauncher). A launch token lives 120 seconds and the Kadi API allows
    | 30 launch tokens a minute for the whole site key, so players are limited too.
    */
    'launch' => [
        'per_minute' => (int) env('GAMES_LAUNCHES_PER_MINUTE', 6),
        // Seconds the play page waits for the game before offering "Open in its own tab".
        'fallback_after_seconds' => 8,
    ],

    // Most titles a player can heart.
    'favourites_limit' => 100,

    'featured' => [
        'dragon_fire_fortune',
        'pharaohs_fortune',
        'berry_blast',
        'buffalo_thunder_gold',
        'mermaid_millions',
        'sizzling_sevens',
        'werewolf_moon_wilds',
        'golden_koi_fortune',
        'wild_safari',
        'krakens_gold',
        'lucky_lanterns',
        'crown_of_embers',
    ],

];
