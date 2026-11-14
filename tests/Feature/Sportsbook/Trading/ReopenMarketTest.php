<?php

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;
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

/** A suspended 1X2 of an EPL match 30 minutes in, priced live a moment ago. */
function liveSuspendedMarket(array $market = [], array $fixture = []): Market
{
    $football = Sport::firstWhere('slug', 'football') ?? Sport::factory()->configured('football')->create();
    $epl = Competition::factory()->synced()->for($football)->create(['odds_api_key' => 'soccer_epl']);
    $live = Fixture::factory()->for($epl)->create(['kicks_off_at' => now('UTC')->subMinutes(30), ...$fixture]);

    return Market::factory()->suspended()->for($live)->has(Selection::factory())->create(['source' => MarketSource::Feed, 'priced_at' => now('UTC'), ...$market]);
}

test('in play, staff can reopen a live market with a fresh live price', function () {
    config(['platform.sports_in_play' => true]);
    $market = liveSuspendedMarket();

    app(ReopenMarket::class)->handle($market, User::factory()->create());

    expect($market->fresh()->status)->toBe(MarketStatus::Open);
});

test('in play, a live market stays closed when stale, held for a goal, decided, not covered, or with in play off', function (array $market, array $fixture, bool $inPlay, string $message) {
    config(['platform.sports_in_play' => $inPlay]);
    $live = liveSuspendedMarket($market, $fixture);

    expect(fn () => app(ReopenMarket::class)->handle($live, User::factory()->create()))->toThrow(MarketCannotBeReopened::class, $message);
})->with([
    // Closures: worked out when the test runs, not when the file loads (a full run takes minutes; the hold is 40 s).
    'live price stale' => [fn () => ['priced_at' => now('UTC')->subMinutes(5)], fn () => [], true, 'live odds are out of date'],
    'goal still held' => [fn () => [], fn () => ['score_changed_at' => now('UTC')->subSeconds(10)], true, 'score just changed'],
    'line decided' => [fn () => ['suspension_reason' => SuspensionReason::LineDecided], fn () => [], true, 'already decided'],
    'derived market' => [fn () => ['source' => MarketSource::Derived, 'type' => MarketType::BothTeamsToScore, 'key' => 'btts'], fn () => [], true, 'has kicked off'],
    'late phase' => [fn () => [], fn () => ['kicks_off_at' => now('UTC')->subMinutes(90)], true, 'has kicked off'],
    'in play off' => [fn () => [], fn () => [], false, 'has kicked off'],
]);
