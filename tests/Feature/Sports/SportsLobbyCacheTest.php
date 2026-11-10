<?php

use App\Sportsbook\Catalogue\SportsLobby;
use Illuminate\Support\Facades\Cache;

/*
 * Sentry KADI-Q: the basketball release added `sport` to the cached competitions, and for a minute after the deploy
 * the side menu read the previous release's copy and threw on every page. A cached shape change must never break a page.
 */
test('a copy cached by an earlier release does not break the side menu', function () {
    Cache::put('sports.lobby.competitions', [['name' => 'Premier League', 'slug' => 'premier-league', 'count' => 3]], 60);

    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);

    expect(app(SportsLobby::class)->sports())->toHaveCount(1);
    $this->get('/login')->assertOk();
});

test('a cached competition without a sport is skipped, not fatal', function () {
    Cache::put(SportsLobby::COMPETITIONS_KEY, [
        ['name' => 'Premier League', 'slug' => 'premier-league', 'count' => 3],
        ['name' => 'NBA', 'slug' => 'nba', 'count' => 2, 'sport' => 'basketball', 'popular' => true],
    ], 60);

    expect(app(SportsLobby::class)->sports())->toBe([
        ['slug' => 'basketball', 'name' => 'Basketball', 'icon' => config('sportsbook.sports.basketball.icon'), 'count' => 2],
    ]);
});
