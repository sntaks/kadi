<?php

use App\Predict\LiveState;
use App\Predict\MarketFeed;
use App\Predict\Markets;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/**
 * What the pages read (App\Predict\Markets): short caches, the last good copy during an outage, and the feed's
 * newer live state laid over the cached market.
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
});

test('the list keeps only filters the API accepts and is cached briefly', function () {
    Http::fake(['*/predict/markets*' => Http::response(predictFixture('markets-list-polymarket')['body'])]);
    $markets = app(Markets::class);

    $first = $markets->list(['source' => 'polymarket', 'category' => 'sport', 'sort' => 'made-up', 'page' => 0, 'evil' => 'x']);
    $markets->list(['source' => 'polymarket', 'category' => 'sport', 'sort' => 'nonsense']);

    expect($first['available'])->toBeTrue()
        ->and($first['markets'])->toHaveCount(4)
        ->and($first['markets'][0]->source)->toBe('polymarket');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->data() == [
        'source' => 'polymarket', 'category' => 'sport', 'sort' => 'closing_soon', 'page' => 1, 'per_page' => 24,
    ]);
});

test('an outage serves the last good copy and backs off', function () {
    Http::fake(['*/predict/markets*' => Http::sequence()
        ->push(predictFixture('markets-list-polymarket')['body'])
        ->push(['message' => 'Server Error'], 500),
    ]);
    $markets = app(Markets::class);
    $markets->list();
    $this->travel(11)->seconds();

    expect($markets->list()['markets'])->toHaveCount(4)
        ->and($markets->list()['available'])->toBeTrue();

    // One failed call, then the back-off: no second call.
    Http::assertSentCount(2);
});

test('with nothing cached, an outage is an empty list, never an error', function () {
    Http::fake(['*/predict/markets*' => Http::response(['message' => 'Server Error'], 500)]);

    expect(app(Markets::class)->list())->toMatchArray(['markets' => [], 'available' => false]);
});

test('an unknown market is null', function () {
    Http::fake(['*/predict/markets/*' => Http::response(predictFixture('refusal-market-not-found')['body'], 404)]);

    expect(app(Markets::class)->find(99))->toBeNull();
});

test('a market the feed has seen since takes its live state from the feed', function () {
    Http::fake(['*/predict/markets/2007' => Http::response(predictFixture('market-polymarket-pool')['body'])]);
    $markets = app(Markets::class);

    expect($markets->find(2007)->odds->yesMultiplier)->toBe(2.43);

    $this->travel(1)->seconds();
    $live = predictFixture('market-polymarket-pool')['body']['data'];
    $live['odds']['yes_multiplier'] = 3.1;
    $live['status'] = 'suspended';
    Cache::forever(MarketFeed::recordKey(2007), ['api' => LiveState::fromApi($live, Date::now()), 'hash' => 'h', 'changed_at' => 0]);

    $market = $markets->find(2007);

    expect($market->odds->yesMultiplier)->toBe(3.1)
        ->and($market->isSuspended())->toBeTrue()
        // The text still comes from the market read.
        ->and($market->question)->toStartWith('Will Elon Musk post');
});

test('the betting cut-off is applied when the page reads, at our clock', function () {
    Http::fake(['*/predict/markets/2007' => Http::response(predictFixture('market-polymarket-pool')['body'])]);

    $this->travelTo(Date::parse('2026-11-13T16:45:01+03:00'));

    $market = app(Markets::class)->find(2007);

    expect($market->bettingAvailable)->toBeFalse()
        ->and($market->takesBets(Date::now()))->toBeFalse();
});

test('the history is oldest first and drops points it cannot draw', function () {
    Http::fake(['*/predict/markets/5/history' => Http::response(['success' => true, 'data' => ['market_id' => 5, 'points' => [
        ['at' => '2026-11-13T14:30:00+03:00', 'yes_probability' => 0.4, 'no_probability' => 0.6],
        ['at' => '2026-11-13T14:00:00+03:00', 'yes_probability' => 0.3, 'no_probability' => 0.7],
        ['at' => 'yesterday-ish', 'yes_probability' => 0.5],
        ['at' => '2026-11-13T14:10:00+03:00', 'yes_probability' => 1.7],
    ]]])]);

    expect(app(Markets::class)->history(5))->toBe([
        ['at' => '2026-11-13T11:00:00+00:00', 'yes' => 0.3],
        ['at' => '2026-11-13T11:30:00+00:00', 'yes' => 0.4],
    ]);
});

test('an imported pool market has no Polymarket attribution and bets without a quote', function () {
    Http::fake(['*/predict/markets/2007' => Http::response(predictFixture('market-polymarket-pool')['body'])]);

    $market = app(Markets::class)->find(2007);

    expect($market->isImported())->toBeTrue()
        ->and($market->usesQuote())->toBeFalse()
        ->and($market->odds->isReference)->toBeFalse()
        ->and($market->odds->attribution)->toBeNull()
        ->and($market->externalUrl)->toStartWith('https://polymarket.com/event/');
});
