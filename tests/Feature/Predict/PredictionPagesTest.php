<?php

use App\Livewire\Predict\Lobby;
use App\Livewire\Predict\MarketPage;
use App\Models\User;
use App\Predict\Data\Market;
use App\Predict\MarketFeed;
use App\Predict\Markets;
use App\Support\SiteNavigation;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The lobby (/predictions) and the market page (/predictions/{market}), read from faked API answers.
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
    // A feed that has just answered: no "out of date" warning unless a test wants one.
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
});

/** The live pool-import market, changed. */
function pageMarket(int $id, array $changes = []): array
{
    return array_replace_recursive(predictFixture('market-polymarket-pool')['body']['data'], ['id' => $id, 'event' => null], $changes);
}

function referencePageMarket(int $id, array $changes = []): array
{
    return pageMarket($id, array_replace_recursive([
        'engine' => 'reference',
        'question' => "Reference question {$id}?",
        'betting_closes_at' => '2026-12-30T23:45:00+03:00',
        'odds' => ['is_reference' => true, 'attribution' => 'Reference prices from Polymarket', 'yes_multiplier' => 4.23, 'no_multiplier' => 1.22, 'yes_probability' => 0.225, 'no_probability' => 0.775],
    ], $changes));
}

function fakePredictPages(array $world, array $kadi = [], array $markets = [], int $lastPage = 1): void
{
    $list = fn (array $items) => Http::response(['success' => true, 'data' => [
        'items' => $items, 'pagination' => ['page' => 1, 'per_page' => 24, 'total' => count($items), 'last_page' => $lastPage],
    ]]);
    $routes = [
        '*/predict/markets?*source=custom*' => $list($kadi),
        '*/predict/markets?*' => $list($world),
    ];

    foreach ($markets as $market) {
        $routes["*/predict/markets/{$market['id']}/history"] = Http::response(['success' => true, 'data' => ['market_id' => $market['id'], 'points' => [
            ['at' => '2026-11-13T12:00:00+03:00', 'yes_probability' => 0.3, 'no_probability' => 0.7],
        ]]]);
        $routes["*/predict/markets/{$market['id']}"] = Http::response(['success' => true, 'data' => $market]);
    }

    $routes['*/predict/markets/*'] = Http::response(predictFixture('refusal-market-not-found')['body'], 404);
    Http::fake($routes);
}

test('guests see the lobby with world markets, and the Kadi section only when there are Kadi markets', function () {
    config(['platform.predictions_betting' => false]);
    fakePredictPages([pageMarket(1, ['question' => 'Will it rain in Nairobi?'])]);

    $this->get('/predictions')
        ->assertOk()
        ->assertSee('World markets')
        ->assertSee('Will it rain in Nairobi?')
        ->assertDontSee('Kadi markets')
        ->assertSee('Betting on predictions opens soon');
});

test('markets of one event are one card, others stay apart', function () {
    $event = ['event' => ['id' => 9, 'title' => 'EPL: 2027 Champion']];
    fakePredictPages([
        referencePageMarket(1, $event + ['question' => 'Will Arsenal win the EPL?']),
        pageMarket(2, ['question' => 'A question on its own?']),
        referencePageMarket(3, $event + ['question' => 'Will Chelsea win the EPL?']),
    ]);

    $groups = Livewire::test(Lobby::class)->viewData('worldGroups');

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['title'])->toBe('EPL: 2027 Champion')
        ->and(collect($groups[0]['markets'])->pluck('id')->all())->toBe([1, 3])
        ->and($groups[1]['markets'][0]->id)->toBe(2);
});

test('only fixed-odds markets carry the Polymarket attribution; pool ones say Estimated', function () {
    fakePredictPages([referencePageMarket(1), pageMarket(2)]);

    $html = $this->get('/predictions')->getContent();
    $card = fn (int $id) => Str::between($html, 'data-predict-market="'.$id.'"', '</article>');

    expect($card(1))->toContain('Reference prices from Polymarket')->toContain('Fixed odds')
        ->and($card(2))->not->toContain('Reference prices from Polymarket')->toContain('Estimated');
});

test('a side without a multiplier, or one paying less than the stake, is not on offer', function () {
    fakePredictPages([pageMarket(1, ['odds' => ['yes_multiplier' => 18, 'no_multiplier' => 0.94]])]);

    $this->get('/predictions')
        ->assertSee('18.00x')
        ->assertSee('No: not on offer');
});

test('event rows read only what differs between the questions', function () {
    $mk = fn (int $id, string $q) => Market::fromArray(['id' => $id, 'question' => $q]);

    expect(Markets::outcomeLabels([$mk(1, 'Will Alberto Núñez Feijóo be the next Prime Minister of Spain?'), $mk(2, 'Will Pedro Sánchez be the next Prime Minister of Spain?')]))
        ->toBe([1 => 'Alberto Núñez Feijóo', 2 => 'Pedro Sánchez'])
        // Numbers alone get their neighbours, percentages stand alone.
        ->and(Markets::outcomeLabels([$mk(1, 'US announces end of Iranian blockade by November 15, 2026?'), $mk(2, 'US announces end of Iranian blockade by November 30, 2026?')]))
        ->toBe([1 => 'November 15, 2026', 2 => 'November 30, 2026'])
        ->and(Markets::outcomeLabels([$mk(1, 'Will the Fed upper bound reach 4.5% before 2027?'), $mk(2, 'Will the Fed upper bound reach 3.75% before 2027?')]))
        ->toBe([1 => '4.5%', 2 => '3.75%']);
});

test('cards show time left, and volume only once there is some', function () {
    fakePredictPages([pageMarket(1, ['betting_closes_at' => '2026-11-13T22:40:00+03:00']), pageMarket(2, ['odds' => ['volume' => 11100, 'bets_count' => 19]])]);

    $html = $this->get('/predictions')->getContent();
    $card = fn (int $id) => Str::before(Str::after($html, 'data-predict-market="'.$id.'"'), '</footer>');

    expect($card(1))->toContain('>8h<')->toContain('Estimated')->not->toContain(' vol<')
        ->and($card(2))->toContain('KES 11.1K vol')->toContain('19 bets');
});

test('load more adds the next page under the first, and stops at the last page', function () {
    $page = fn (int $n, array $items, int $last = 3) => Http::response(['success' => true, 'data' => [
        'items' => $items, 'pagination' => ['page' => $n, 'per_page' => 24, 'total' => 5, 'last_page' => $last],
    ]]);
    Http::fake([
        '*/predict/markets?*source=custom*' => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 24, 'total' => 0, 'last_page' => 1]]]),
        '*/predict/markets?*&page=2&*' => $page(2, [pageMarket(3), pageMarket(4)]),
        '*/predict/markets?*&page=3&*' => $page(3, [pageMarket(5), pageMarket(2)]),
        '*/predict/markets?*' => $page(1, [pageMarket(1), pageMarket(2)]),
    ]);

    $lobby = Livewire::test(Lobby::class)
        ->assertSee('Showing 2 of 5')
        ->assertSeeHtml('data-predict-load-more');

    $lobby->call('loadMore')->assertSee('Showing 4 of 5');

    // A market that moved to a later page between reads shows once; the last page ends the list.
    $lobby->call('loadMore')
        ->assertSee('Showing 5 of 5')
        ->assertDontSeeHtml('data-predict-load-more')
        ->assertSet('pages', 3);

    $lobby->call('loadMore')->assertSet('pages', 3);
    expect(collect($lobby->viewData('world')['markets'])->pluck('id')->all())->toBe([1, 2, 3, 4, 5]);
});

test('the next page is read after the response, so load more finds it ready', function () {
    fakePredictPages([pageMarket(1)], lastPage: 3);

    $this->get('/predictions')->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '&page=2&') && str_contains($request->url(), 'source=polymarket'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '&page=3&'));
});

test('changing the category or the sort starts again from the first page', function () {
    fakePredictPages([pageMarket(1)], lastPage: 4);

    Livewire::test(Lobby::class)
        ->call('loadMore')->assertSet('pages', 2)
        ->call('setCategory', 'sport')->assertSet('pages', 1)
        ->call('loadMore')->call('setSort', 'new')->assertSet('pages', 1);
});

test('a suspended market says betting is paused', function () {
    fakePredictPages([pageMarket(1, ['status' => 'suspended', 'betting_available' => false])]);

    $this->get('/predictions')->assertSee('Betting paused');
});

test('the filters read the query string and only accepted values reach the API', function () {
    fakePredictPages([pageMarket(1)]);

    Livewire::withQueryParams(['category' => 'politics', 'sort' => 'trending'])
        ->test(Lobby::class)
        ->call('setCategory', 'made-up')
        ->assertSet('category', '');

    Http::assertSent(fn ($request) => ($request->data()['category'] ?? null) === 'politics' && ($request->data()['sort'] ?? null) === 'trending');
});

test('the poll redraws only when the feed has moved', function () {
    fakePredictPages([pageMarket(1)]);
    $page = Livewire::test(Lobby::class);
    Http::assertSentCount(2);

    $page->call('sync');
    Http::assertSentCount(2);

    Cache::forever(MarketFeed::VERSION_KEY, 5);
    $this->travel(11)->seconds();
    $page->call('sync');

    expect($page->get('seenVersion'))->toBe(5);
});

test('an out-of-date feed is said so', function () {
    Cache::forget(MarketFeed::OK_AT_KEY);
    fakePredictPages([pageMarket(1)]);

    $this->get('/predictions')->assertSee('Prices may be out of date');
});

test('the market page shows the rules once, the cut-off in Nairobi time, the chart and the source link', function () {
    $market = referencePageMarket(7, ['description' => 'Resolves to Yes if it happens.', 'resolution_criteria' => 'Resolves to Yes if it happens.']);
    fakePredictPages([], markets: [$market]);

    $html = $this->get('/predictions/7')->assertOk()->getContent();
    // The chart comes straight after the page (wire:init), so the first response never waits on the history.
    expect($html)->toContain('wire:init="loadChart"')->not->toContain('data-predict-chart');
    $html = Livewire::test(MarketPage::class, ['market' => 7])->call('loadChart')->html();

    expect(substr_count($html, 'Resolves to Yes if it happens.'))->toBe(1)
        ->and($html)->toContain('Wed 30 Dec 2026, 23:45')
        ->toContain('aria-label="Chance of Yes over time: from 30% to 23%"')
        ->toContain('Reference prices from Polymarket')
        ->toContain('href="https://polymarket.com/event/')
        ->toContain('rel="noopener nofollow"')
        ->toContain('referrerpolicy="no-referrer"');
});

test('a pool market explains the estimate and shows no attribution', function () {
    fakePredictPages([], markets: [pageMarket(2007)]);

    $this->get('/predictions/2007')
        ->assertSee('This is a pool: the multiplier is an estimate')
        ->assertDontSee('Reference prices from Polymarket');
});

test('an unknown market is a 404', function () {
    fakePredictPages([]);

    $this->get('/predictions/99')->assertNotFound();
});

test('a guest gets sign-in links for the sides; a player without betting gets disabled buttons; a tester can pick', function () {
    fakePredictPages([], markets: [pageMarket(2007)]);

    $guest = $this->get('/predictions/2007')->getContent();
    expect($guest)->toContain('data-needs-account');

    // Betting not open yet (phpunit.xml pins it on, as in production once opened).
    config(['platform.predictions_betting' => false]);
    $player = User::factory()->create();
    Livewire::actingAs($player)->test(MarketPage::class, ['market' => 2007])
        ->assertSee('Betting on predictions opens soon')
        ->assertSeeHtml('data-can-bet="0"');

    config(['predict.betting_testers' => [$player->id]]);
    Livewire::actingAs($player)->test(MarketPage::class, ['market' => 2007])
        ->assertSeeHtml('data-can-bet="1"')
        ->assertSeeHtml('predict-pick');
});

test('past the cut-off the market page closes betting on our clock', function () {
    fakePredictPages([], markets: [pageMarket(2007)]);
    $this->travelTo(Date::parse('2026-11-13T16:45:01+03:00'));

    $this->get('/predictions/2007')->assertSee('Betting closed');
});

test('the pages and the menu entry exist only while predictions are on', function () {
    fakePredictPages([pageMarket(1)]);

    expect(collect((new SiteNavigation(null))->products())->pluck('label'))->toContain('Predictions')
        ->and(collect((new SiteNavigation(null))->groups())->pluck('key'))->toContain('predictions');

    config(['platform.predictions' => false]);

    $this->get('/predictions')->assertNotFound();
    $this->get('/predictions/1')->assertNotFound();
    expect(collect((new SiteNavigation(null))->products())->pluck('label'))->not->toContain('Predictions');
});

test('no page shows the site key', function () {
    fakePredictPages([pageMarket(1)], markets: [pageMarket(1)]);

    expect($this->get('/predictions')->getContent())->not->toContain('site-key')
        ->and($this->get('/predictions/1')->getContent())->not->toContain('site-key');
});
