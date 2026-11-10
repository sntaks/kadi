<?php

use App\Sportsbook\Models\Competition;
use Database\Seeders\Sportsbook\CompetitionSeeder;

test('switches syncing on for the default football and basketball competitions in display order', function () {
    $this->seed(CompetitionSeeder::class);

    expect(Competition::query()->synced()->orderBy('sort_order')->pluck('odds_api_key')->all())->toBe([
        'soccer_epl',
        'soccer_spain_la_liga',
        'soccer_italy_serie_a',
        'soccer_germany_bundesliga',
        'soccer_france_ligue_one',
        'soccer_uefa_champs_league',
        'basketball_nba',
        'basketball_euroleague',
    ]);

    // Each competition joins its own sport, from its key's prefix.
    expect(Competition::firstWhere('odds_api_key', 'basketball_nba')->sport->slug)->toBe('basketball')
        ->and(Competition::firstWhere('odds_api_key', 'soccer_epl')->sport->slug)->toBe('football');
});

test('keeps names already taken from the feed and does not duplicate on a second run', function () {
    $this->seed(CompetitionSeeder::class);
    Competition::where('odds_api_key', 'soccer_epl')->update(['name' => 'English Premier League (feed)']);

    $this->seed(CompetitionSeeder::class);

    expect(Competition::count())->toBe(8)
        ->and(Competition::firstWhere('odds_api_key', 'soccer_epl')->name)->toBe('English Premier League (feed)');
});
