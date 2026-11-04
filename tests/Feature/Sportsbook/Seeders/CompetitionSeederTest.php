<?php

use App\Sportsbook\Models\Competition;
use Database\Seeders\Sportsbook\CompetitionSeeder;

test('switches syncing on for the six default competitions in display order', function () {
    $this->seed(CompetitionSeeder::class);

    expect(Competition::query()->synced()->orderBy('sort_order')->pluck('odds_api_key')->all())->toBe([
        'soccer_epl',
        'soccer_spain_la_liga',
        'soccer_italy_serie_a',
        'soccer_germany_bundesliga',
        'soccer_france_ligue_one',
        'soccer_uefa_champs_league',
    ]);
});

test('keeps names already taken from the feed and does not duplicate on a second run', function () {
    $this->seed(CompetitionSeeder::class);
    Competition::where('odds_api_key', 'soccer_epl')->update(['name' => 'English Premier League (feed)']);

    $this->seed(CompetitionSeeder::class);

    expect(Competition::count())->toBe(6)
        ->and(Competition::firstWhere('odds_api_key', 'soccer_epl')->name)->toBe('English Premier League (feed)');
});
