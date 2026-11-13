<?php

use App\Events\PredictMarketsChanged;
use App\Predict\MarketFeed;
use App\Support\PlatformHealth;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * The feed worker (App\Predict\MarketFeed): cursor, paging, change detection, the changes the API never announces,
 * and the broadcast. Live answers from tests/Fixtures/predict, faked with Http::fake.
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Event::fake([PredictMarketsChanged::class]);
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
});

/** One market from the live pool-import fixture, with changes. */
function feedMarket(int $id, array $changes = []): array
{
    $market = predictFixture('market-polymarket-pool')['body']['data'];

    return array_replace_recursive($market, ['id' => $id], $changes);
}

function referenceMarket(int $id, array $changes = []): array
{
    return feedMarket($id, array_replace_recursive([
        'engine' => 'reference',
        'betting_closes_at' => '2026-12-30T23:45:00+03:00',
        'odds' => ['is_reference' => true, 'attribution' => 'Reference prices from Polymarket', 'yes_multiplier' => 4.23, 'no_multiplier' => 1.22],
    ], $changes));
}

function updatesAnswer(array $markets, string $serverTime = '2026-11-13T14:37:17+03:00', bool $hasMore = false, ?string $pricesAt = null): array
{
    return ['success' => true, 'data' => [
        'server_time' => $serverTime,
        'has_more' => $hasMore,
        'reference_prices_at' => $pricesAt ?? $serverTime,
        'markets' => $markets,
    ]];
}

function listAnswer(array $markets, int $page = 1, int $lastPage = 1): array
{
    return ['success' => true, 'data' => [
        'items' => $markets,
        'pagination' => ['page' => $page, 'per_page' => 100, 'total' => count($markets), 'last_page' => $lastPage],
    ]];
}

/** Give the feed a cursor, as if it had started earlier. */
function feedCursor(string $since = '2026-11-13T11:37:00Z'): void
{
    Cache::forever(MarketFeed::CURSOR_KEY, ['since' => $since, 'after_id' => null, 'next_since' => null]);
}

/** @return list<array<string, mixed>> every market broadcast so far */
function broadcastMarkets(): array
{
    return Event::dispatched(PredictMarketsChanged::class)->flatMap(fn (array $call) => $call[0]->markets)->all();
}

test('without a cursor the feed reads the whole list, then follows updates from just before it started', function () {
    Http::fake([
        '*/predict/markets?page=1*' => Http::response(listAnswer([feedMarket(1), referenceMarket(2)], 1, 2)),
        '*/predict/markets?page=2*' => Http::response(listAnswer([feedMarket(3)], 2, 2)),
    ]);

    $result = app(MarketFeed::class)->tick();

    expect($result)->toBe(['ran' => true, 'ok' => true, 'changed' => 3])
        ->and(Cache::get(MarketFeed::INDEX_KEY))->toBe([1, 2, 3])
        ->and(Cache::get(MarketFeed::CURSOR_KEY))->toBe(['since' => '2026-11-13T11:36:20Z', 'after_id' => null, 'next_since' => null])
        ->and(app(MarketFeed::class)->isStale())->toBeFalse()
        ->and(collect(broadcastMarkets())->pluck('id')->all())->toBe([1, 2, 3]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'per_page=100'));
});

test('the first answer sets the next since, and has_more pages the same window by id', function () {
    feedCursor();
    $first = predictFixture('updates-has-more')['body'];
    $second = predictFixture('updates-has-more-page-2')['body'];
    Http::fake([
        '*/predict/updates*after_id=2*' => Http::response($second),
        '*/predict/updates*' => Http::response($first),
    ]);

    app(MarketFeed::class)->tick();

    $sent = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->data())->all();

    expect($sent)->toBe([
        ['since' => '2026-11-13T11:37:00Z'],
        ['since' => '2026-11-13T11:37:00Z', 'after_id' => 2],
    ])
        // The second page's later server_time is not the next since: only the window's first answer is.
        ->and(Cache::get(MarketFeed::CURSOR_KEY))->toBe(['since' => '2026-11-13T14:37:17+03:00', 'after_id' => null, 'next_since' => null])
        ->and(Cache::get(MarketFeed::INDEX_KEY))->toBe([1, 2, 1218]);
});

test('a window bigger than one tick carries on from the last id next tick', function () {
    config(['predict.feed.max_pages' => 1]);
    feedCursor();
    Http::fake([
        '*/predict/updates*after_id=2*' => Http::response(predictFixture('updates-has-more-page-2')['body']),
        '*/predict/updates*' => Http::response(predictFixture('updates-has-more')['body']),
    ]);

    app(MarketFeed::class)->tick();

    expect(Cache::get(MarketFeed::CURSOR_KEY))->toBe([
        'since' => '2026-11-13T11:37:00Z', 'after_id' => 2, 'next_since' => '2026-11-13T14:37:17+03:00',
    ]);

    app(MarketFeed::class)->tick();

    expect(Cache::get(MarketFeed::CURSOR_KEY))->toBe(['since' => '2026-11-13T14:37:17+03:00', 'after_id' => null, 'next_since' => null]);
});

test('a market that comes back with the same figures is not broadcast again', function () {
    feedCursor();
    // A new capture time alone is not a change.
    Http::fake(['*/predict/updates*' => Http::sequence()
        ->push(updatesAnswer([feedMarket(7)]))
        ->push(updatesAnswer([feedMarket(7, ['odds' => ['updated_at' => '2026-11-13T14:37:30+03:00']])], '2026-11-13T14:37:22+03:00'))
        ->push(updatesAnswer([feedMarket(7, ['odds' => ['yes_multiplier' => 2.5]])], '2026-11-13T14:37:27+03:00')),
    ]);
    $feed = app(MarketFeed::class);

    expect($feed->tick()['changed'])->toBe(1)
        ->and($feed->tick()['changed'])->toBe(0)
        ->and($feed->version())->toBe(1)
        ->and($feed->tick()['changed'])->toBe(1)
        ->and($feed->version())->toBe(2)
        ->and(broadcastMarkets()[1]['odds']['yes_multiplier'])->toBe(2.5);
});

test('the betting cut-off passing is broadcast though the API never announces it', function () {
    feedCursor();
    Http::fake(['*/predict/updates*' => Http::sequence()
        ->push(updatesAnswer([feedMarket(7, ['betting_closes_at' => '2026-11-13T14:40:00+03:00'])]))
        ->push(updatesAnswer([], '2026-11-13T14:40:05+03:00')),
    ]);
    $feed = app(MarketFeed::class);
    $feed->tick();

    expect($feed->live(7)['shown']['betting_available'])->toBeTrue();

    $this->travelTo(Date::parse('2026-11-13T14:40:01+03:00'));
    $feed->tick();

    expect($feed->live(7)['shown']['betting_available'])->toBeFalse()
        ->and(broadcastMarkets())->toHaveCount(2)
        ->and(broadcastMarkets()[1])->toMatchArray(['id' => 7, 'betting_available' => false]);
});

test('stalled Polymarket prices take fixed-odds multipliers off, never pool ones, and bring them back', function () {
    feedCursor();
    // The API's own clock: prices 50 s older than its server_time, even though our clock agrees with neither.
    Http::fake(['*/predict/updates*' => Http::sequence()
        ->push(updatesAnswer([feedMarket(1), referenceMarket(2)], '2026-11-13T14:37:17+03:00', pricesAt: '2026-11-13T14:36:27+03:00'))
        ->push(updatesAnswer([], '2026-11-13T14:37:22+03:00')),
    ]);
    $feed = app(MarketFeed::class);
    $feed->tick();

    expect($feed->referenceStalled())->toBeTrue()
        ->and($feed->live(2)['shown']['odds'])->toMatchArray(['yes_multiplier' => null, 'no_multiplier' => null])
        ->and($feed->live(1)['shown']['odds'])->toMatchArray(['yes_multiplier' => 2.43, 'no_multiplier' => 1.42]);

    $feed->tick();

    expect($feed->referenceStalled())->toBeFalse()
        ->and($feed->live(2)['shown']['odds'])->toMatchArray(['yes_multiplier' => 4.23, 'no_multiplier' => 1.22])
        ->and(collect(broadcastMarkets())->where('id', 2))->toHaveCount(2);
});

test('a refused cursor starts again from the list', function () {
    feedCursor('2026-11-01T00:00:00Z');
    Http::fake([
        '*/predict/updates*' => Http::response(predictFixture('refusal-since-too-old')['body'], 422),
        '*/predict/markets*' => Http::response(listAnswer([feedMarket(5)])),
    ]);

    expect(app(MarketFeed::class)->tick()['ok'])->toBeTrue()
        ->and(Cache::get(MarketFeed::CURSOR_KEY)['since'])->toBe('2026-11-13T11:36:20Z')
        ->and(Cache::get(MarketFeed::INDEX_KEY))->toBe([5]);
});

test('a bootstrap drops markets the list no longer shows', function () {
    Cache::forever(MarketFeed::INDEX_KEY, [9]);
    Cache::forever(MarketFeed::recordKey(9), ['api' => ['id' => 9], 'hash' => 'x', 'changed_at' => 0]);
    Http::fake(['*/predict/markets*' => Http::response(listAnswer([feedMarket(5)]))]);

    app(MarketFeed::class)->tick();

    expect(Cache::get(MarketFeed::INDEX_KEY))->toBe([5])
        ->and(Cache::get(MarketFeed::recordKey(9)))->toBeNull();
});

test('no answer keeps the cursor, and after 30 seconds the feed is stale', function () {
    feedCursor();
    Http::fake(['*/predict/updates*' => Http::sequence()
        ->push(updatesAnswer([feedMarket(7)]))
        ->push(['message' => 'Server Error'], 500),
    ]);
    $feed = app(MarketFeed::class);
    $feed->tick();
    $this->travel(31)->seconds();

    expect($feed->tick())->toBe(['ran' => true, 'ok' => false, 'changed' => 0])
        ->and(Cache::get(MarketFeed::CURSOR_KEY)['since'])->toBe('2026-11-13T14:37:17+03:00')
        ->and($feed->isStale())->toBeTrue();
});

test('only one process ticks at a time', function () {
    Cache::lock(MarketFeed::LOCK_KEY, 60)->get();

    expect(app(MarketFeed::class)->tick())->toBe(['ran' => false, 'ok' => false, 'changed' => 0]);
    Http::assertNothingSent();
});

test('the broadcast is public market data only, in batches, on predict.markets', function () {
    config(['predict.feed.broadcast_batch' => 2]);
    feedCursor();
    Http::fake(['*/predict/updates*' => Http::response(updatesAnswer([
        feedMarket(1, ['odds' => ['secret_extra' => 'x']]), feedMarket(2), feedMarket(3),
    ]))]);

    app(MarketFeed::class)->tick();

    $events = Event::dispatched(PredictMarketsChanged::class)->map(fn (array $call) => $call[0]);

    expect($events)->toHaveCount(2)
        ->and($events->first()->broadcastOn()[0]->name)->toBe('predict.markets')
        ->and($events->first()->broadcastAs())->toBe('markets.changed')
        ->and(array_keys($events->first()->broadcastWith()['markets'][0]))
        ->toBe(['id', 'status', 'betting_available', 'betting_closes_at', 'odds', 'changed_at'])
        ->and($events->first()->broadcastWith()['markets'][0]['odds'])->not->toHaveKey('secret_extra');
});

test('a broadcast failure never stops the feed', function () {
    Broadcast::shouldReceive('event')->andThrow(new RuntimeException('Reverb is down'));
    feedCursor();
    Http::fake(['*/predict/updates*' => Http::response(updatesAnswer([feedMarket(7)]))]);

    expect(app(MarketFeed::class)->tick())->toMatchArray(['ok' => true, 'changed' => 1]);
});

test('the worker idles while the prediction pages are off', function () {
    config(['platform.predictions' => false]);

    $this->artisan('predict:feed', ['--once' => true])->assertSuccessful();
    Http::assertNothingSent();
});

test('one worker tick follows the feed', function () {
    feedCursor();
    Http::fake(['*/predict/updates*' => Http::response(updatesAnswer([feedMarket(7)]))]);

    $this->artisan('predict:feed', ['--once' => true])->assertSuccessful();

    expect(app(MarketFeed::class)->live(7))->not->toBeNull();
});

test('platform health reports a feed with no good answer while predictions are on', function () {
    // The other checks read the game catalogue: let them fail quietly.
    Http::fake(['*' => Http::response([], 500)]);
    $health = fn () => app(PlatformHealth::class)->check()['predict_feed'];

    expect($health()['context'])->toBe(['minutes' => -1]);

    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
    expect($health())->toBeNull();

    $this->travel(3)->minutes();
    expect($health())->toMatchArray(['context' => ['minutes' => 3]]);

    config(['platform.predictions' => false]);
    expect($health())->toBeNull();
});
