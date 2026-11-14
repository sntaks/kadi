<?php

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\OddsSnapshot;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    config(['sportsbook.odds.margin' => 0.06, 'sportsbook.odds.minimum_bookmakers' => 3]);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response(oddsApiFixture('odds-soccer_epl'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '98486'])]);
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
});

function syncEplOdds(Competition $competition): void
{
    app(SyncOdds::class)->handle($competition, [MarketType::MatchResult, MarketType::Totals]);
}

/**
 * @return array<string, string|null>
 */
function offeredPrices(Market $market): array
{
    return $market->selections()->orderBy('id')->get()->mapWithKeys(fn (Selection $selection) => [$selection->name => $selection->price()])->all();
}

test('prices 1X2 from the median bookmaker after margins and opens the market', function () {
    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-arsenal-spurs')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Open)
        ->and($market->bookmaker_count)->toBe(3)
        ->and(offeredPrices($market))->toBe(['Arsenal' => '1.88', 'Draw' => '3.77', 'Tottenham Hotspur' => '3.77'])
        ->and(OddsSnapshot::whereIn('selection_id', $market->selections()->select('id'))->count())->toBe(3);
});

test('takes a goal line from the feed only when enough bookmakers price it, leaving thin lines to the model', function () {
    syncEplOdds($this->competition);

    $fixture = Fixture::firstWhere('external_id', 'evt-arsenal-spurs');
    $twoAndAHalf = $fixture->markets()->where('key', 'totals:2.5')->sole();
    expect(offeredPrices($twoAndAHalf))->toBe(['Over 2.5' => '1.88', 'Under 2.5' => '1.88'])
        ->and($twoAndAHalf->status)->toBe(MarketStatus::Open)
        ->and($twoAndAHalf->source)->toBe(MarketSource::Feed)
        ->and($fixture->markets()->where('key', 'totals:3.5')->sole()->source)->toBe(MarketSource::Derived);
});

test('keeps a thinly priced feed goal line with the model on later syncs', function () {
    syncEplOdds($this->competition);
    syncEplOdds($this->competition);

    $threeAndAHalf = Fixture::firstWhere('external_id', 'evt-arsenal-spurs')->markets()->where('key', 'totals:3.5')->sole();
    expect($threeAndAHalf->source)->toBe(MarketSource::Derived)
        ->and($threeAndAHalf->status)->toBe(MarketStatus::Open);
});

test('suspends a 1X2 market that fewer than three bookmakers price', function () {
    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-chelsea-everton')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Suspended)
        ->and($market->suspension_reason)->toBe(SuspensionReason::InsufficientBookmakers)
        ->and($market->bookmaker_count)->toBe(2)
        ->and($market->selections()->exists())->toBeFalse();
});

test('does not duplicate markets or price history when nothing changed', function () {
    syncEplOdds($this->competition);
    $counts = [Fixture::count(), Market::count(), OddsSnapshot::count()];

    syncEplOdds($this->competition);

    expect([Fixture::count(), Market::count(), OddsSnapshot::count()])->toBe($counts)
        ->and($counts[1])->toBe(10);
});

test('keeps an admin override while the feed price underneath it updates', function () {
    $fixture = Fixture::factory()->for($this->competition)->create(['external_id' => 'evt-arsenal-spurs']);
    $market = Market::factory()->for($fixture)->create();
    $home = Selection::factory()->for($market)->overridden('2.50')->create(['outcome' => 'home', 'feed_price' => '1.70']);

    syncEplOdds($this->competition);

    $home->refresh();
    expect($home->feed_price)->toBe('1.88')
        ->and($home->override_price)->toBe('2.50')
        ->and($home->price())->toBe('2.50')
        ->and($home->snapshots()->exists())->toBeFalse();
});

test('reopens markets suspended for pricing reasons but not ones an admin suspended', function (SuspensionReason $reason, MarketStatus $expected) {
    $fixture = Fixture::factory()->for($this->competition)->create(['external_id' => 'evt-arsenal-spurs']);
    $market = Market::factory()->for($fixture)->suspended($reason)->create();

    syncEplOdds($this->competition);

    expect($market->fresh()->status)->toBe($expected);
})->with([
    'stale odds' => [SuspensionReason::StaleOdds, MarketStatus::Open],
    'too few bookmakers' => [SuspensionReason::InsufficientBookmakers, MarketStatus::Open],
    'suspended by an admin' => [SuspensionReason::Manual, MarketStatus::Suspended],
]);

test('keeps markets closed once the fixture has kicked off', function () {
    $this->travelTo(Date::parse('2026-10-20 14:30:00', 'UTC'));

    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-arsenal-spurs')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Suspended)
        ->and($market->suspension_reason)->toBe(SuspensionReason::KickedOff);
});

/*
 * Sentry KADI-B/KADI-C: one fixture lookup per event and one market lookup per market. A re-sync reads the fixtures,
 * their markets and selections once, whatever the number of events and lines.
 */
test('a re-sync reads fixtures, markets and selections once, not once per event or market', function () {
    app(SyncOdds::class)->handle($this->competition, [MarketType::Totals]);
    $marketsBefore = Market::count();

    DB::enableQueryLog();
    app(SyncOdds::class)->handle($this->competition, [MarketType::Totals]);

    $reads = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_starts_with($sql, 'select * from'));
    expect($reads->filter(fn (string $sql): bool => str_contains($sql, 'from "sb_fixtures"')))->toHaveCount(1)
        ->and($reads->filter(fn (string $sql): bool => str_contains($sql, 'from "sb_markets"')))->toHaveCount(1)
        ->and($reads->filter(fn (string $sql): bool => str_contains($sql, 'from "sb_selections"')))->toHaveCount(1)
        ->and(Market::count())->toBe($marketsBefore);
});
