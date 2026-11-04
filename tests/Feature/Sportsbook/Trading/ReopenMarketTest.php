<?php

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Trading\MarketCannotBeReopened;
use App\Sportsbook\Trading\ReopenMarket;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['sportsbook.odds.stale_after_hours' => 26]);
});

test('reopens a freshly priced market for an upcoming fixture and logs who did it', function () {
    $admin = User::factory()->create();
    $market = Market::factory()->suspended()->has(Selection::factory())->create();

    app(ReopenMarket::class)->handle($market, $admin);

    expect($market->fresh()->status)->toBe(MarketStatus::Open)
        ->and($market->fresh()->suspension_reason)->toBeNull()
        ->and(Activity::query()->where('event', 'market-reopened')->sole()->causer->is($admin))->toBeTrue();
});

test('reopening a 1X2 reopens derived markets suspended by hand, but not stale ones', function () {
    $matchResult = Market::factory()->suspended()->has(Selection::factory())->create();
    $fresh = Market::factory()->for($matchResult->fixture)->suspended()->has(Selection::factory())
        ->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    $stale = Market::factory()->for($matchResult->fixture)->suspended()->pricedHoursAgo(30)->has(Selection::factory())
        ->create(['type' => MarketType::CorrectScore, 'key' => 'correct_score', 'source' => MarketSource::Derived]);

    app(ReopenMarket::class)->handle($matchResult, User::factory()->create());

    expect($fresh->fresh()->status)->toBe(MarketStatus::Open)
        ->and($stale->fresh()->status)->toBe(MarketStatus::Suspended);
});

test('refuses to reopen a market that cannot take bets right now', function (Closure $makeMarket, string $message) {
    $market = $makeMarket();

    expect(fn () => app(ReopenMarket::class)->handle($market, User::factory()->create()))
        ->toThrow(MarketCannotBeReopened::class, $message);

    expect($market->fresh()->status)->toBe(MarketStatus::Suspended);
})->with([
    'kicked off' => [fn () => Market::factory()->for(Fixture::factory()->kickedOff())->suspended()->has(Selection::factory())->create(), 'has kicked off'],
    'stale odds' => [fn () => Market::factory()->pricedHoursAgo(30)->suspended()->has(Selection::factory())->create(), 'out of date'],
    'never priced' => [fn () => Market::factory()->suspended()->create(), 'no prices yet'],
]);
