<?php

use App\Events\LiveFixturesUpdated;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\OddsApiCall;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\Sync\SuspendStaleMarkets;
use App\Sportsbook\Sync\SyncLive;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * Plan §5 phase 5a: in-play scores and prices. Markets may be priced and open in play; betting on them comes in 5b.
 */

beforeEach(function () {
    useLiveOddsApi();
    config([
        'platform.sports_in_play' => true,
        'sportsbook.odds.margin' => 0.06,
        'sportsbook.in_play.margin' => 0.08,
        'sportsbook.odds.minimum_bookmakers' => 3,
        'sportsbook.odds_api.market_bookmakers.spreads' => 'pinnacle,bovada,coolbet',
    ]);
    Event::fake([LiveFixturesUpdated::class]);
    $this->travelTo(Date::parse('2026-11-21 15:30:00', 'UTC'));

    $football = Sport::factory()->configured('football')->create();
    $this->competition = Competition::factory()->synced()->for($football)->create(['odds_api_key' => 'soccer_epl']);
    // Kicked off 30 minutes ago.
    $this->fixture = Fixture::factory()->for($this->competition)->create([
        'external_id' => 'evt-ars-che', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'kicks_off_at' => Date::parse('2026-11-21 15:00:00', 'UTC'),
    ]);

    // What the feed answers: set per test with liveFeed().
    $this->score = [0, 0];
    $this->completed = false;
    $this->prices = ['home' => 2.1, 'draw' => 3.2, 'away' => 3.6];
    Http::fake([
        'odds-api.test/v4/sports/soccer_epl/scores*' => fn () => Http::response([[
            'id' => 'evt-ars-che', 'sport_key' => 'soccer_epl', 'commence_time' => '2026-11-21T15:00:00Z', 'completed' => $this->completed,
            'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'last_update' => null,
            'scores' => [['name' => 'Arsenal', 'score' => (string) $this->score[0]], ['name' => 'Chelsea', 'score' => (string) $this->score[1]]],
        ]], 200, ['x-requests-last' => '1', 'x-requests-remaining' => '90000']),
        'odds-api.test/v4/sports/soccer_epl/odds*' => fn (Request $request) => Http::response([[
            'id' => 'evt-ars-che', 'sport_key' => 'soccer_epl', 'commence_time' => '2026-11-21T15:00:00Z', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea',
            'bookmakers' => array_map(fn (string $book) => ['key' => $book, 'markets' => isset($request->data()['bookmakers'])
                ? [['key' => 'spreads', 'outcomes' => [['name' => 'Arsenal', 'price' => 1.9, 'point' => -0.5], ['name' => 'Chelsea', 'price' => 1.9, 'point' => 0.5]]]]
                : [
                    ['key' => 'h2h', 'outcomes' => [['name' => 'Arsenal', 'price' => $this->prices['home']], ['name' => 'Draw', 'price' => $this->prices['draw']], ['name' => 'Chelsea', 'price' => $this->prices['away']]]],
                    ['key' => 'totals', 'outcomes' => [['name' => 'Over', 'price' => 1.9, 'point' => 2.5], ['name' => 'Under', 'price' => 1.9, 'point' => 2.5]]],
                ]], isset($request->data()['bookmakers']) ? ['pinnacle', 'bovada', 'coolbet'] : ['williamhill', 'skybet', 'betfair_ex_uk']),
        ]], 200, ['x-requests-last' => isset($request->data()['bookmakers']) ? '1' : '2', 'x-requests-remaining' => '90000']),
    ]);
});

/** One of the live match's markets. */
function liveMarket(string $key): ?Market
{
    return Market::where('fixture_id', test()->fixture->id)->firstWhere('key', $key);
}

function tick(): array
{
    return app(SyncLive::class)->handle();
}

test('a tick buys the live score, then the in-play markets, priced with the in-play margin and open', function () {
    // The 1X2 was suspended at kick-off; live pricing reopens it.
    Market::factory()->for($this->fixture)->create(['status' => MarketStatus::Suspended, 'suspension_reason' => SuspensionReason::KickedOff]);

    expect(tick())->toBe(['competitions' => 1, 'fixtures' => 1]);

    expect(liveMarket('h2h'))->status->toBe(MarketStatus::Open)
        ->and(liveMarket('totals:2.5')->status)->toBe(MarketStatus::Open)
        ->and(liveMarket('spreads:-0.5')->status)->toBe(MarketStatus::Open)
        ->and($this->fixture->fresh())->home_score->toBe(0)->away_score->toBe(0)->score_changed_at->toBeNull();

    // Scores (1) + main markets (2) + handicaps (1), all counted as live spend.
    expect(OddsApiCall::where('purpose', OddsApiPurpose::Live)->sum('credits_spent'))->toBe(4)
        ->and(OddsApiCall::where('endpoint', 'scores')->sole()->purpose)->toBe(OddsApiPurpose::Live);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/scores') && ! isset($request->data()['daysFrom']));

    // A higher margin than pre-match: the same prices pay less.
    $inPlayHome = (float) liveMarket('h2h')->selections()->where('outcome', 'home')->value('feed_price');
    config(['sportsbook.in_play.margin' => 0.06]);
    $this->travelTo(now()->addSeconds(40));
    tick();
    expect((float) liveMarket('h2h')->selections()->where('outcome', 'home')->value('feed_price'))->toBeGreaterThan($inPlayHome);

    Event::assertDispatched(LiveFixturesUpdated::class, fn (LiveFixturesUpdated $event): bool => $event->fixtures[0]['id'] === $this->fixture->id
        && $event->fixtures[0]['home_score'] === 0
        && collect($event->fixtures[0]['markets'])->every(fn (array $market): bool => $market['open']));
});

test('a goal holds every market of the match until prices fetched after the hold', function () {
    tick();
    $this->score = [1, 0];
    $this->travelTo(now()->addSeconds(40));

    tick();

    expect($this->fixture->fresh())->home_score->toBe(1)->score_changed_at->not->toBeNull()
        ->and(liveMarket('h2h'))->status->toBe(MarketStatus::Suspended)->suspension_reason->toBe(SuspensionReason::ScoreChanged)
        ->and(liveMarket('totals:2.5')->suspension_reason)->toBe(SuspensionReason::ScoreChanged);

    // The next tick, 40 s later, prices after the hold: open again.
    $this->travelTo(now()->addSeconds(40));
    tick();

    expect(liveMarket('h2h')->status)->toBe(MarketStatus::Open);
});

test('a goal line the total has passed closes for the rest of the match', function () {
    tick();
    $this->score = [2, 1];
    $this->travelTo(now()->addSeconds(40));
    tick();
    $this->travelTo(now()->addSeconds(80));
    tick();

    expect(liveMarket('totals:2.5'))->status->toBe(MarketStatus::Suspended)->suspension_reason->toBe(SuspensionReason::LineDecided)
        ->and(liveMarket('h2h')->status)->toBe(MarketStatus::Open);
});

test('live prices that stop arriving close within one tick, and reopen when they come back', function () {
    tick();
    $this->travelTo(now()->addSeconds(91));

    app(SuspendStaleMarkets::class)->handle();

    expect(liveMarket('h2h'))->status->toBe(MarketStatus::Suspended)->suspension_reason->toBe(SuspensionReason::LiveFeedStale);

    tick();

    expect(liveMarket('h2h')->status)->toBe(MarketStatus::Open);
});

test('football closes for good at 85 minutes, and when the feed says the match is over', function () {
    tick();
    $this->travelTo(Date::parse('2026-11-21 16:25:00', 'UTC'));

    tick();

    expect(liveMarket('h2h'))->status->toBe(MarketStatus::Suspended)->suspension_reason->toBe(SuspensionReason::LatePhase);

    $this->travelTo(now()->addSeconds(40));
    tick();
    expect(liveMarket('h2h')->status)->toBe(MarketStatus::Suspended);
});

test('a match the feed reports completed closes and is no longer bought', function () {
    tick();
    $this->completed = true;
    $this->travelTo(now()->addSeconds(40));

    tick();

    expect($this->fixture->fresh()->feed_completed_at)->not->toBeNull()
        ->and(liveMarket('h2h')->suspension_reason)->toBe(SuspensionReason::LatePhase);

    Http::fake();
    $calls = OddsApiCall::count();
    $this->travelTo(now()->addSeconds(40));
    expect(tick())->toBe(['competitions' => 0, 'fixtures' => 0])
        ->and(OddsApiCall::count())->toBe($calls);
});

test('derived markets, extras and competitions outside the in-play list still close at kick-off', function () {
    $derived = Market::factory()->for($this->fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived, 'priced_at' => now()]);
    $extra = Market::factory()->for($this->fixture)->create(['type' => MarketType::Totals, 'line' => 4.5, 'key' => 'totals:4.5', 'source' => MarketSource::EventFeed, 'priced_at' => now()]);
    $other = Fixture::factory()->for(Competition::factory()->synced()->for(Sport::firstWhere('slug', 'football'))->create(['odds_api_key' => 'soccer_spl']))
        ->create(['kicks_off_at' => now()->subMinutes(10)]);
    $otherMarket = Market::factory()->for($other)->create(['priced_at' => now()]);

    tick();

    expect($derived->fresh()->suspension_reason)->toBe(SuspensionReason::KickedOff)
        ->and($extra->fresh()->suspension_reason)->toBe(SuspensionReason::KickedOff)
        ->and($otherMarket->fresh()->suspension_reason)->toBe(SuspensionReason::KickedOff)
        ->and(liveMarket('h2h')->status)->toBe(MarketStatus::Open);
});

test('with in play off nothing is bought and kicked-off markets close as before', function () {
    config(['platform.sports_in_play' => false]);
    $market = Market::factory()->for($this->fixture)->create(['priced_at' => now()]);
    Http::fake();

    expect(tick())->toBe(['competitions' => 0, 'fixtures' => 0]);
    app(SuspendStaleMarkets::class)->handle();

    Http::assertNothingSent();
    expect($market->fresh()->suspension_reason)->toBe(SuspensionReason::KickedOff);
});

test('nothing is bought when no match is in play', function () {
    $this->fixture->update(['kicks_off_at' => now()->addHour()]);
    Http::fake();

    expect(tick())->toBe(['competitions' => 0, 'fixtures' => 0]);

    Http::assertNothingSent();
});

test('the daily sync leaves a match in play to the live sync', function () {
    tick();
    $before = liveMarket('h2h')->priced_at;
    $this->travelTo(now()->addSeconds(30));

    app(SyncOdds::class)->handle($this->competition, [MarketType::MatchResult]);

    expect(liveMarket('h2h'))->status->toBe(MarketStatus::Open)->priced_at->toEqual($before);
});

test('past the live allowance the tick logs and stops, and live markets go stale', function () {
    config(['sportsbook.odds_api.allowances.live' => 0]);
    Log::spy();

    expect(tick())->toBe(['competitions' => 0, 'fixtures' => 0]);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'In-play tick stopped'))->once();
});

test('the command ticks every tick_seconds, skipping the 20-second runs in between', function () {
    $this->artisan('sportsbook:sync-live')->expectsOutputToContain('Live tick: 1 matches')->assertSuccessful();
    $calls = OddsApiCall::count();

    $this->travelTo(now()->addSeconds(20));
    $this->artisan('sportsbook:sync-live')->assertSuccessful();
    expect(OddsApiCall::count())->toBe($calls);

    $this->travelTo(now()->addSeconds(20));
    $this->artisan('sportsbook:sync-live')->expectsOutputToContain('Live tick')->assertSuccessful();
    expect(OddsApiCall::count())->toBeGreaterThan($calls);

    config(['platform.sports_in_play' => false]);
    $this->artisan('sportsbook:sync-live')->expectsOutputToContain('In play is off')->assertSuccessful();
});

test('basketball closes about two minutes from the end', function () {
    $basketball = Sport::factory()->configured('basketball')->create();
    $euroleague = Competition::factory()->synced()->for($basketball)->create(['odds_api_key' => 'basketball_euroleague']);
    $game = Fixture::factory()->for($euroleague)->create(['kicks_off_at' => now()->subMinutes(101)]);
    $market = Market::factory()->for($game)->create(['priced_at' => now()]);

    app(SuspendStaleMarkets::class)->handle();

    expect($market->fresh()->suspension_reason)->toBe(SuspensionReason::LatePhase);
});

test('an open live market still takes no bets until live placement ships (phase 5b)', function () {
    tick();
    $selection = liveMarket('h2h')->selections()->firstWhere('outcome', 'home');

    expect(liveMarket('h2h')->status)->toBe(MarketStatus::Open)
        ->and(app(BettableSelections::class)->unavailableReason($selection))->toBe('This match has started.');
});
