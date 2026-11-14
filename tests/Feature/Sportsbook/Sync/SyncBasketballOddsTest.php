<?php

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    config(['sportsbook.odds.margin' => 0.06, 'sportsbook.odds.minimum_bookmakers' => 3]);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));
    Http::fake(['odds-api.test/v4/sports/basketball_nba/odds*' => Http::response(oddsApiFixture('odds-basketball_nba'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '98486'])]);
    $this->competition = Competition::factory()->synced()->create([
        'odds_api_key' => 'basketball_nba',
        'sport_id' => Sport::factory()->configured('basketball'),
    ]);
});

test('a basketball match result is a two-way Winner with no draw', function () {
    app(SyncOdds::class)->handle($this->competition, [MarketType::MatchResult, MarketType::Totals]);

    $market = Fixture::firstWhere('external_id', 'evt-knicks-bucks')->markets()->where('key', 'h2h')->with('selections')->sole();

    expect($market->status)->toBe(MarketStatus::Open)
        ->and($market->selections->pluck('outcome')->all())->toBe(['home', 'away'])
        ->and($market->selections->pluck('name')->all())->toBe(['New York Knicks', 'Milwaukee Bucks'])
        ->and($market->hasDraw())->toBeFalse()
        ->and($market->name())->toBe('Winner');
});

test('basketball takes its points line from the feed and derives no football markets', function () {
    app(SyncOdds::class)->handle($this->competition, [MarketType::MatchResult, MarketType::Totals]);

    $fixture = Fixture::firstWhere('external_id', 'evt-knicks-bucks');

    expect($fixture->markets()->pluck('key')->sort()->values()->all())->toBe(['h2h', 'totals:221.5'])
        ->and($fixture->markets()->where('source', MarketSource::Derived)->count())->toBe(0)
        ->and($fixture->markets()->where('key', 'totals:221.5')->sole()->name())->toBe('Over/Under 221.5');
});
