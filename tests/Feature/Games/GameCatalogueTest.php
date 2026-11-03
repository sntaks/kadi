<?php

use App\Games\GameArtwork;
use App\Games\GameCatalogue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const CATALOGUE_API = 'https://kadi-api.test/api/v1/';

function catalogueProduct(string $code, string $game, string $name, string $status = 'active'): array
{
    return ['code' => $code, 'game' => $game, 'name' => $name, 'revenue_model' => 'house_banked', 'status' => $status,
        'min_stake' => null, 'max_stake' => null, 'max_payout' => null, 'winnings_tax_rate' => 0];
}

/** The shape `GET /games` returns, trimmed to a few products. */
function catalogueBody(): array
{
    return ['data' => [
        ['code' => 'scratch', 'name' => 'Scratch Cards', 'launch_url' => 'https://scratch.kadi.online', 'status' => 'active', 'sort_order' => 3, 'products' => [
            catalogueProduct('scratch_fruit_fiesta', 'scratch', 'Fruit Fiesta'),
        ]],
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [
            catalogueProduct('kadi', 'kadi', 'Kadi'),
            catalogueProduct('kadi_tournament', 'kadi', 'Kadi Tournament'),
            catalogueProduct('kadi_jackpot', 'kadi', 'Kadi Jackpot'),
        ]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            catalogueProduct('slots_dragon_fire_fortune', 'slots', 'Dragon Fire Fortune'),
            catalogueProduct('slots_triple_jackpot_fruits', 'slots', 'Triple Jackpot Fruits'),
            catalogueProduct('slots_brand_new_title', 'slots', 'Brand New Title'),
            catalogueProduct('slots_pirate_gold', 'slots', 'Pirate Gold', 'suspended'),
        ]],
    ]];
}

function catalogue(): GameCatalogue
{
    return app(GameCatalogue::class);
}

beforeEach(function () {
    config(['services.kadi_api.url' => CATALOGUE_API, 'services.kadi_api.key' => 'site-key']);
});

test('games come from the API in lobby order with the site key', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    expect(catalogue()->games()->keys()->all())->toBe(['kadi', 'slots', 'scratch'])
        ->and(catalogue()->game('slots')->launchUrl)->toBe('https://slots.kadi.online');

    Http::assertSent(fn (Request $request) => $request->url() === CATALOGUE_API.'games'
        && $request->hasHeader('x-api-key', 'site-key'));
});

test('kadi is one tile and its products are modes, not titles', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    $kadi = catalogue()->game('kadi');

    expect($kadi->hasTitles())->toBeFalse()
        ->and($kadi->products)->toBe(['kadi', 'kadi_tournament', 'kadi_jackpot'])
        ->and($kadi->tagline)->not->toBeNull();
});

test('titles join API names with artwork, collection and badges from config', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    $dragon = catalogue()->title('slots_dragon_fire_fortune');
    $jackpot = catalogue()->title('slots_triple_jackpot_fruits');
    $scratch = catalogue()->title('scratch_fruit_fiesta');

    expect($dragon->name)->toBe('Dragon Fire Fortune')
        ->and($dragon->collection)->toBe('legends')
        ->and($dragon->featured)->toBeTrue()
        ->and($dragon->thumbnail)->toEndWith('/games/thumbs/slots_dragon_fire_fortune.webp')
        ->and($jackpot->hasBadge('jackpot'))->toBeTrue()
        ->and($jackpot->collection)->toBe('fruit')
        ->and($scratch->game)->toBe('scratch')
        ->and($scratch->collection)->toBeNull()
        ->and($scratch->isPresented())->toBeTrue();
});

test('a product the API offers but config does not know shows as a plain fallback tile', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    $new = catalogue()->title('slots_brand_new_title');

    expect($new)->not->toBeNull()
        ->and($new->name)->toBe('Brand New Title')
        ->and($new->thumbnail)->toBeNull()
        ->and($new->collection)->toBeNull()
        ->and($new->badges)->toBe([])
        ->and($new->isPresented())->toBeFalse();
});

test('the API decides what is playable: suspended products and games and config-only titles never show', function () {
    $body = catalogueBody();
    $body['data'][] = ['code' => 'crash', 'name' => 'Crash', 'launch_url' => 'https://crash.kadi.online', 'status' => 'suspended', 'sort_order' => 4, 'products' => []];
    Http::fake([CATALOGUE_API.'games' => Http::response($body)]);

    expect(catalogue()->title('slots_pirate_gold'))->toBeNull()
        ->and(catalogue()->game('crash'))->toBeNull()
        ->and(catalogue()->title('slots_egypt_gold'))->toBeNull() // in config, not offered
        ->and(catalogue()->collection('legends')->pluck('code')->all())->toBe(['slots_dragon_fire_fortune'])
        ->and(catalogue()->featured()->pluck('code')->all())->toBe(['slots_dragon_fire_fortune']);
});

test('the catalogue is cached for five minutes', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    catalogue()->games();
    app()->forgetInstance(GameCatalogue::class);
    catalogue()->games();
    Http::assertSentCount(1);

    $this->travel(301)->seconds();
    app()->forgetInstance(GameCatalogue::class);
    catalogue()->games();
    Http::assertSentCount(2);
});

test('when the API is down the last good copy is served, and retried at most once a minute', function () {
    Http::fake([CATALOGUE_API.'games' => Http::sequence()
        ->push(catalogueBody())
        ->push(['message' => 'down'], 503)
        ->push(catalogueBody())]);

    catalogue()->games();
    $this->travel(301)->seconds();
    app()->forgetInstance(GameCatalogue::class);

    expect(catalogue()->games()->keys()->all())->toBe(['kadi', 'slots', 'scratch']);

    app()->forgetInstance(GameCatalogue::class);
    catalogue()->games();
    Http::assertSentCount(2);

    $this->travel(61)->seconds();
    app()->forgetInstance(GameCatalogue::class);
    catalogue()->games();
    Http::assertSentCount(3);
});

test('with the API down and nothing cached the lobby is empty, not an error', function (mixed $response) {
    Http::fake([CATALOGUE_API.'games' => $response]);

    expect(catalogue()->games())->toBeEmpty()
        ->and(catalogue()->titles())->toBeEmpty();
})->with([
    'server error' => fn () => Http::response(['message' => 'down'], 500),
    'unreachable' => fn () => Http::failedConnection(),
    'malformed' => fn () => Http::response(['data' => 'nope']),
    'empty list' => fn () => Http::response(['data' => []]),
    'object instead of list' => fn () => Http::response(['data' => ['items' => catalogueBody()['data']]]),
    'games without code or launch url' => fn () => Http::response(['data' => [['name' => 'Slots']]]),
]);

test('a 200 that is not a usable catalogue never replaces the last good copy', function () {
    Http::fake([CATALOGUE_API.'games' => Http::sequence()
        ->push(catalogueBody())
        ->push(['data' => []])]);

    catalogue()->games();
    $this->travel(301)->seconds();
    app()->forgetInstance(GameCatalogue::class);

    expect(catalogue()->games()->keys()->all())->toBe(['kadi', 'slots', 'scratch'])
        ->and(Cache::get(GameCatalogue::STALE_KEY))->toHaveCount(3);
});

test('while another process refreshes, the stale copy is served without calling the API', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    catalogue()->games();
    catalogue()->refresh();
    $lock = Cache::lock(GameCatalogue::LOCK_KEY, 10);
    $lock->get();

    expect(catalogue()->games()->keys()->all())->toBe(['kadi', 'slots', 'scratch']);
    Http::assertSentCount(1);

    $lock->release();
});

test('games whose launch URL is not https on an allowed host are left out', function (string $url) {
    $body = catalogueBody();
    $body['data'][2]['launch_url'] = $url;
    Http::fake([CATALOGUE_API.'games' => Http::response($body)]);

    expect(catalogue()->game('slots'))->toBeNull()
        ->and(catalogue()->game('kadi'))->not->toBeNull();
})->with([
    'script' => ['javascript:alert(1)'],
    'plain http' => ['http://slots.kadi.online'],
    'other site' => ['https://kadi.online.evil.example'],
    'look-alike' => ['https://evilkadi.online'],
    'credentials' => ['https://user:pass@slots.kadi.online'],
]);

test('allowed launch hosts match the apex and subdomains only', function () {
    expect(GameCatalogue::isAllowedLaunchUrl('https://kadi.online/play'))->toBeTrue()
        ->and(GameCatalogue::isAllowedLaunchUrl('https://scratch.kadi.online'))->toBeTrue()
        ->and(GameCatalogue::isAllowedLaunchUrl('https://SLOTS.Kadi.Online/launch'))->toBeTrue()
        ->and(GameCatalogue::isAllowedLaunchUrl('https://kadi.onlinex'))->toBeFalse();
});

test('a configured title whose thumbnail is not built yet is still presented', function () {
    config(['games.artwork.thumbnails' => 'games/not-built']);
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    $dragon = catalogue()->title('slots_dragon_fire_fortune');

    expect($dragon->thumbnail)->toBeNull()
        ->and($dragon->isPresented())->toBeTrue()
        ->and($dragon->collection)->toBe('legends');
});

test('refresh asks the API again but keeps the stale copy as a fallback', function () {
    Http::fake([CATALOGUE_API.'games' => Http::response(catalogueBody())]);

    catalogue()->games();
    catalogue()->refresh();

    expect(Cache::has(GameCatalogue::FRESH_KEY))->toBeFalse()
        ->and(Cache::has(GameCatalogue::STALE_KEY))->toBeTrue();

    catalogue()->games();
    Http::assertSentCount(2);
});

test('config presents all 70 slots and 4 scratch cards, each with its original artwork', function () {
    $products = GameArtwork::configuredProducts();

    expect(array_count_values($products))->toBe(['scratch' => 4, 'slots' => 70]);

    foreach ($products as $code => $game) {
        $source = GameArtwork::source($game, $code);

        expect($source)->not->toBeNull()
            ->and(public_path($source))->toBeFile()
            ->and(public_path(GameArtwork::thumbnail($code)))->toBeFile();
    }
});

test('every slot is in exactly one of the six collections', function () {
    $collections = config('games.collections');
    $all = array_merge(...array_column($collections, 'products'));

    expect($collections)->toHaveCount(6)
        ->and($all)->toHaveCount(70)
        ->and(array_unique($all))->toHaveCount(70);
});

test('badged and featured titles are configured products', function () {
    $known = array_keys(GameArtwork::configuredProducts());

    foreach ([...config('games.featured'), ...array_merge(...array_values(config('games.badges')))] as $code) {
        expect($known)->toContain($code);
    }
});
