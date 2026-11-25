<?php

use App\Games\GameCatalogue;
use App\Games\Lobby;
use App\Support\SiteNavigation;
use Illuminate\Support\Facades\Http;

/*
 * Coin Flip: a casino game from KadiApi, one tile and page per product like slots (config/games.php). Configured artwork is
 * used when available; new API products still get a plain fallback tile.
 */
const COIN_API = 'https://kadi-api.test/api/v1/';

function coinCatalogue(array $coinProducts): array
{
    $games = [
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [
            ['code' => 'kadi', 'name' => 'Kadi', 'status' => 'active'],
        ]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            ['code' => 'slots_berry_blast', 'name' => 'Berry Blast', 'status' => 'active'],
        ]],
    ];

    if ($coinProducts !== []) {
        $games[] = ['code' => 'coinflip', 'name' => 'Coin Flip', 'launch_url' => 'https://coin.kadi.online/launch.php', 'status' => 'active', 'sort_order' => 4, 'products' => array_map(
            fn (array $product) => [...$product, 'status' => 'active'],
            $coinProducts,
        )];
    }

    return ['data' => $games];
}

beforeEach(function () {
    config([
        'services.kadi_api.url' => COIN_API,
        'services.kadi_api.key' => 'site-key',
        'platform.token_launch' => true,
        'platform.deep_link' => true,
    ]);

    $this->coinProducts = [['code' => 'gold_rush', 'name' => 'Gold Rush']];

    Http::preventStrayRequests();
    Http::fake(fn ($request) => $request->url() === COIN_API.'games'
        ? Http::response(coinCatalogue($this->coinProducts))
        : Http::response([], 200));
});

test('Coin Flip shows in the casino as its own tile, with its configured artwork', function () {
    $title = app(GameCatalogue::class)->title('gold_rush');

    expect($title)->not->toBeNull()
        ->and($title->game)->toBe('coinflip')
        ->and($title->thumbnail)->toBe(asset('games/thumbs/gold_rush.webp'))
        ->and(app(Lobby::class)->filter('all', null)->pluck('key'))->toContain('gold_rush');

    $this->get(route('casino'))->assertOk()->assertSee('Gold Rush')
        ->assertSee('src="'.asset('games/thumbs/gold_rush.webp').'"', false);
    $this->get(route('casino', ['filter' => 'coin-flip']))->assertOk()->assertSee('Gold Rush')->assertDontSee('Berry Blast');
});

// The casino tab shows only while the API offers Coin Flip; the side menu link is always there (it never reads the catalogue).
test('the casino page offers a Coin flip tab', function () {
    expect(app(Lobby::class)->filters())->toHaveKey('coin-flip');

    $this->get(route('casino'))->assertSee(route('casino', ['filter' => 'coin-flip']), false);
});

test('each product the API adds is its own tile, with no code change', function () {
    $this->coinProducts = [
        ['code' => 'coin_flip', 'name' => 'Coin Flip'],
        ['code' => 'coin_flip_turbo', 'name' => 'Coin Flip Turbo'],
    ];

    expect(app(Lobby::class)->coinFlips()->pluck('key')->all())->toBe(['coin_flip', 'coin_flip_turbo']);
});

test('without Coin Flip in the API there is no tile or tab, and its page is an empty shelf', function () {
    $this->coinProducts = [];

    expect(app(GameCatalogue::class)->title('gold_rush'))->toBeNull()
        ->and(app(Lobby::class)->filters())->not->toHaveKey('coin-flip');

    $tabs = fn (string $html) => Str::between($html, 'aria-label="Casino shelves"', '</nav>');
    expect($tabs($this->get(route('casino'))->assertOk()->assertDontSee('Gold Rush')->getContent()))
        ->not->toContain(route('casino', ['filter' => 'coin-flip']));

    // The side menu links here without reading the catalogue, so the page stays, as an empty shelf.
    $this->get(route('casino', ['filter' => 'coin-flip']))->assertOk()
        ->assertSee('Nothing to play here right now')
        ->assertDontSee('Gold Rush');
});

test('Coin Flip products without configured artwork keep a fallback tile', function () {
    $this->coinProducts = [['code' => 'new_table', 'name' => 'New Table']];

    $title = app(GameCatalogue::class)->title('new_table');

    expect($title)->not->toBeNull()
        ->and($title->thumbnail)->toBeNull();

    $this->get(route('casino', ['filter' => 'coin-flip']))->assertOk()
        ->assertSee('class="nt-art-fallback" aria-hidden="true">New Table</span>', false);
});

test('the casino side menu always links to Coin flip, Roulette and Jet, without reading the catalogue', function () {
    $this->coinProducts = [];
    $items = collect(collect((new SiteNavigation(null))->groups())->firstWhere('key', 'casino')['items']);

    expect($items->pluck('label')->all())->toContain('Coin flip', 'Roulette', 'Jet')
        ->and($items->firstWhere('label', 'Coin flip')['href'])->toBe(route('casino', ['filter' => 'coin-flip']))
        ->and($items->firstWhere('label', 'Roulette')['href'])->toBe(route('casino', ['filter' => 'roulette']))
        ->and($items->firstWhere('label', 'Jet')['href'])->toBe(route('casino', ['filter' => 'jet']));
});
