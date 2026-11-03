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
    | (Kadi singles, tournaments, jackpots). `artwork` maps a product code to
    | its original under public/: `{name}` is the code without `prefix`.
    */
    'games' => [
        'kadi' => [
            'titles' => false,
            // Kadi keeps its own launch: a form POST of `ggid` (the player's account_no) to
            // services.kadi_api.play_url. Every other game uses a single-use API launch token.
            'launch' => 'ggid',
            // Only inside the site's frame once Kadi's web build is confirmed single-threaded
            // (plan §4a). Until then Play Kadi goes through the launcher and opens in its own page.
            'frame' => (bool) env('KADI_IN_FRAME', false),
            'tagline' => 'Classic Kenyan Kadi: singles, tournaments and jackpots.',
            // Players seated right now per mode (App\Games\KadiTables). The only seat counts the site shows.
            'totals_url' => env('KADI_TOTALS_URL') ?: 'https://gameapi.kadi.online/kadi/get_user_totals.php',
        ],
        'slots' => [
            'titles' => true,
            'tagline' => 'Seventy themed slots, one wallet.',
            'prefix' => 'slots_',
            'artwork' => 'games/slots_games/{name}.webp',
        ],
        'scratch' => [
            'titles' => true,
            'tagline' => 'Scratch the foil, see the prize.',
            'prefix' => 'scratch_',
            'artwork_map' => [
                'scratch_fantasy_quest' => 'games/scratch_games/fantasy.png',
                'scratch_fruit_fiesta' => 'games/scratch_games/fruit.png',
                'scratch_lucky_leprechaun_charms' => 'games/scratch_games/leprechaun.png',
                'scratch_safari_lotto' => 'games/scratch_games/safari.png',
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
                'slots_crown_of_embers',
                'slots_dragon_fire_fortune',
                'slots_dragon_realm',
                'slots_dragons_coin_hoard',
                'slots_fortune_fan',
                'slots_golden_koi_fortune',
                'slots_lucky_lanterns',
                'slots_mystic_forest',
                'slots_neon_city',
                'slots_space_odyssey',
                'slots_wizards_wild_reels',
            ],
        ],
        'pharaoh' => [
            'label' => "Pharaoh's vault",
            'products' => [
                'slots_curse_of_the_pyramid',
                'slots_desert_jewels',
                'slots_egypt_gold',
                'slots_eye_of_the_sun',
                'slots_golden_idol_quest',
                'slots_jungle_relic_hunter',
                'slots_lost_temple',
                'slots_nile_treasures',
                'slots_pharaohs_fortune',
                'slots_scarab_gold',
                'slots_secrets_of_the_sunken_ruins',
                'slots_tomb_raiders_riches',
                'slots_torchlight_temple',
            ],
        ],
        'seas' => [
            'label' => 'High seas',
            'products' => [
                'slots_captains_chest',
                'slots_compass_of_fortune',
                'slots_diamond_cascade_fortune',
                'slots_explorers_gold_rush',
                'slots_gold_rush_gems',
                'slots_krakens_gold',
                'slots_mermaid_millions',
                'slots_ocean_treasure',
                'slots_pearl_divers_delight',
                'slots_pirate_gold',
                'slots_royal_gems',
                'slots_the_lost_map_legacy',
                'slots_treasure_tide',
                'slots_treasure_trail_expedition',
            ],
        ],
        'wild' => [
            'label' => 'Untamed',
            'products' => [
                'slots_buffalo_thunder_gold',
                'slots_canyon_quest_jackpot',
                'slots_frozen_fortune',
                'slots_jade_tiger_riches',
                'slots_jungle_quest',
                'slots_mountain_peak_treasure',
                'slots_silver_stake_showdown',
                'slots_tropical_paradise',
                'slots_wild_safari',
                'slots_wild_west_showdown',
            ],
        ],
        'dark' => [
            'label' => 'After dark',
            'products' => [
                'slots_blood_moon_vampires',
                'slots_count_draculas_treasure_vault',
                'slots_crimson_fangs_fortune',
                'slots_cursed_cauldron_jackpot',
                'slots_gothic_curse_of_the_crypt',
                'slots_halloween',
                'slots_hexed_reels',
                'slots_midnight_coven_riches',
                'slots_midnight_moon_wilds',
                'slots_werewolf_moon_wilds',
                'slots_witch_hunters_bounty',
            ],
        ],
        'fruit' => [
            'label' => 'Fruit stand',
            'products' => [
                'slots_berry_blast',
                'slots_candy_kingdom',
                'slots_cherry_bomb_deluxe',
                'slots_coffee_delight',
                'slots_farm_fortune',
                'slots_fruits',
                'slots_golden_bell_rush',
                'slots_lucky_bar_blitz',
                'slots_lucky_sevens',
                'slots_sizzling_sevens',
                'slots_triple_jackpot_fruits',
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
            'slots_canyon_quest_jackpot',
            'slots_cursed_cauldron_jackpot',
            'slots_triple_jackpot_fruits',
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
        'slots_dragon_fire_fortune',
        'slots_pharaohs_fortune',
        'slots_berry_blast',
        'slots_buffalo_thunder_gold',
        'slots_mermaid_millions',
        'slots_sizzling_sevens',
        'slots_werewolf_moon_wilds',
        'slots_golden_koi_fortune',
        'slots_wild_safari',
        'slots_krakens_gold',
        'slots_lucky_lanterns',
        'slots_crown_of_embers',
    ],

];
