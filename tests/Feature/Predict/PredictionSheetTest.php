<?php

use App\Livewire\Predict\MarketPanel;
use App\Livewire\Predict\Trending;
use App\Predict\Data\Market;
use App\Predict\MarketFeed;
use App\Predict\Markets;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The prediction sheet (App\Livewire\Predict\MarketPanel), the landing's trending shelf, and the list priming each
 * market's own copy so the sheet opens without a call.
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
});

function sheetMarket(int $id, array $changes = []): array
{
    return array_replace_recursive(predictFixture('market-polymarket-pool')['body']['data'], ['id' => $id, 'event' => null, 'question' => "Question {$id}?"], $changes);
}

function sheetList(array $items): array
{
    return ['success' => true, 'data' => ['items' => $items, 'pagination' => ['page' => 1, 'per_page' => 24, 'total' => count($items), 'last_page' => 1]]];
}

test('a market read in a list opens in the sheet with no call of its own', function () {
    Http::fake(['*/predict/markets?*' => Http::response(sheetList([sheetMarket(5)]))]);

    app(Markets::class)->list();
    Http::assertSentCount(1);

    expect(app(Markets::class)->find(5)->question)->toBe('Question 5?');
    Http::assertSentCount(1);
});

test('the sheet shows the market with its rules and pick panel, and no chart', function () {
    Http::fake(['*/predict/markets/5' => Http::response(['success' => true, 'data' => sheetMarket(5)])]);

    Livewire::test(MarketPanel::class)->call('show', 5)
        ->assertSee('Market rules')
        ->assertSee('This is a pool')
        ->assertSeeHtml('data-predict-chance="yes"')
        ->assertDontSeeHtml('data-predict-chart');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/history'));
});

test('a guest\'s sign-in links in the sheet hand over to the sign-in sheet and return to the market', function () {
    Http::fake(['*/predict/markets/5' => Http::response(['success' => true, 'data' => sheetMarket(5)])]);

    $html = Livewire::test(MarketPanel::class)->call('show', 5)->html();

    // "Sign in to predict" and both sides: signin.js opens the sign-in sheet; the prediction sheet closes first.
    expect(substr_count($html, 'x-on:click="$dispatch(\'predict-panel-handoff\')"'))->toBe(3)
        ->and(preg_match_all('/\sdata-needs-account\b/', $html))->toBe(3)
        ->and($html)->toContain('x-on:predict-panel-handoff.window="isOpen && handOff()"');
});

test('an unknown market says so in the sheet', function () {
    Http::fake(['*/predict/markets/*' => Http::response(predictFixture('refusal-market-not-found')['body'], 404)]);

    Livewire::test(MarketPanel::class)->call('show', 99)->assertSee('Market not available');
});

test('the sheet is on prediction pages and the landing, not elsewhere', function () {
    Http::fake([
        '*/predict/markets?*' => Http::response(sheetList([sheetMarket(5)])),
        '*/predict/markets/5/history' => Http::response(['success' => true, 'data' => ['market_id' => 5, 'points' => []]]),
        '*/predict/markets/5' => Http::response(['success' => true, 'data' => sheetMarket(5)]),
        '*' => Http::response([], 500),
    ]);

    expect($this->get('/predictions')->getContent())->toContain('data-predict-panel')
        ->and($this->get('/predictions/5')->getContent())->toContain('data-predict-panel')
        ->and($this->get('/')->getContent())->toContain('data-predict-panel')
        ->and($this->get('/faq')->getContent())->not->toContain('data-predict-panel');

    config(['platform.predictions' => false]);
    expect($this->get('/')->getContent())->not->toContain('data-predict-panel');
});

test('cards open markets in the sheet, and "+N more" opens the event list from the page itself', function () {
    $event = ['event' => ['id' => 1, 'title' => 'EPL: 2027 Champion']];
    Http::fake(['*/predict/markets?*' => Http::response(sheetList([
        sheetMarket(1, $event), sheetMarket(2, $event), sheetMarket(3, $event), sheetMarket(4),
    ]))]);

    $html = $this->get('/predictions')->getContent();

    expect($html)->toContain('$openMarket($event, 4')
        ->toContain('<template data-event-rows>')
        ->toContain('$openEvent(')
        ->toContain('+1 more');
});

test('the landing shelf shows trending markets, one per event, after the page', function () {
    $event = ['event' => ['id' => 1, 'title' => 'EPL: 2027 Champion']];
    Http::fake(['*/predict/markets?*' => Http::response(sheetList([
        sheetMarket(1, $event), sheetMarket(2, $event), sheetMarket(3), sheetMarket(4), sheetMarket(5), sheetMarket(6),
    ]))]);

    Livewire::test(Trending::class)
        ->assertViewHas('markets', fn (array $markets) => collect($markets)->pluck('id')->all() === [1, 3, 4, 5]);

    Http::assertSent(fn ($request) => ($request->data()['sort'] ?? null) === 'trending');
});

test('the landing renders without waiting on the prediction API', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $this->get('/')->assertOk();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/predict/'));
});

test('pick keeps the API order and skips repeated events', function () {
    $m = fn (int $id, ?string $event) => Market::fromArray(['id' => $id, 'question' => "Q{$id}?", 'event' => $event ? ['title' => $event] : null]);

    expect(collect(Trending::pick([$m(1, 'A'), $m(2, 'A'), $m(3, null), $m(4, 'B'), $m(5, null), $m(6, 'C')]))->pluck('id')->all())
        ->toBe([1, 3, 4, 5]);
});
