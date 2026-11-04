<?php

use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    config(['sportsbook.timezone' => 'Africa/Nairobi']);
    // 12:00 in Nairobi.
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));
});

function listed(string $tab): array
{
    return app(FixtureListing::class)->forTab($tab, 'h2h')->pluck('home_team')->all();
}

test('splits today and tomorrow by the Nairobi calendar day', function () {
    pricedFixture(['home_team' => 'Tonight', 'kicks_off_at' => '2026-10-18 18:00:00']);
    pricedFixture(['home_team' => 'After midnight in Nairobi', 'kicks_off_at' => '2026-10-18 22:00:00']);
    pricedFixture(['home_team' => 'Tomorrow afternoon', 'kicks_off_at' => '2026-10-19 12:00:00']);

    expect(listed('today'))->toBe(['Tonight'])
        ->and(listed('tomorrow'))->toBe(['After midnight in Nairobi', 'Tomorrow afternoon']);
});

test('lists only upcoming fixtures from synced competitions that have markets', function () {
    pricedFixture(['home_team' => 'Listed']);
    pricedFixture(['home_team' => 'Kicked off', 'kicks_off_at' => now()->subMinute()]);
    Fixture::factory()->for(Competition::factory()->synced())->create(['home_team' => 'No markets']);
    $unsynced = pricedFixture(['home_team' => 'Unsynced competition']);
    $unsynced->competition->update(['is_synced' => false]);

    expect(listed('upcoming'))->toBe(['Listed']);
});

test('orders highlights by competition order, then kick-off', function () {
    $second = pricedFixture(['home_team' => 'Second competition, early', 'kicks_off_at' => now()->addHour()]);
    $second->competition->update(['sort_order' => 2]);
    $first = pricedFixture(['home_team' => 'First competition, late', 'kicks_off_at' => now()->addDays(2)]);
    $first->competition->update(['sort_order' => 1]);

    expect(listed('highlights'))->toBe(['First competition, late', 'Second competition, early']);
});

test('lists matches in play for up to two and a quarter hours after kick-off', function () {
    pricedFixture(['home_team' => 'In play', 'kicks_off_at' => now()->subMinutes(80)]);
    pricedFixture(['home_team' => 'Long finished', 'kicks_off_at' => now()->subHours(3)]);
    pricedFixture(['home_team' => 'Not started', 'kicks_off_at' => now()->addMinutes(5)]);

    expect(app(FixtureListing::class)->inPlay()->pluck('home_team')->all())->toBe(['In play']);
});

test('finds upcoming fixtures by team or competition name', function () {
    pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);
    $leagueMatch = pricedFixture(['home_team' => 'Lens', 'away_team' => 'Lille']);
    $leagueMatch->competition->update(['name' => 'Ligue 1 - France']);

    expect(app(FixtureListing::class)->search('chels')->pluck('home_team')->all())->toBe(['Arsenal'])
        ->and(app(FixtureListing::class)->search('ligue')->pluck('home_team')->all())->toBe(['Lens']);
});

test('treats SQL wildcards in a search as literal text', function () {
    pricedFixture(['home_team' => 'Arsenal']);

    expect(app(FixtureListing::class)->search('%%'))->toHaveCount(0)
        ->and(app(FixtureListing::class)->search('__'))->toHaveCount(0);
});
