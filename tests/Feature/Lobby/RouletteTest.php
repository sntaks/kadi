<?php

use App\Games\GameCatalogue;
use App\Games\Lobby;
use Illuminate\Support\Facades\Http;

/*
 * Roulette: a casino game from KadiApi, one tile and page per product like Coin Flip (config/games.php). Configured artwork is
 * used when available; new API products still get a plain fallback tile.
 */
const ROULETTE_API = 'https://kadi-api.test/api/v1/';

function rouletteCatalogue(array $products): array
{
    $games = [
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            ['code' => 'slots_berry_blast', 'name' => 'Berry Blast', 'status' => 'active'],
        ]],
    ];

    if ($products !== []) {
        $games[] = ['code' => 'roulette', 'name' => 'Roulette', 'launch_url' => 'https://roulette.kadi.online/launch.php', 'status' => 'active', 'sort_order' => 6, 'products' => array_map(
            fn (array $product) => [...$product, 'status' => 'active'],
            $products,
        )];
    }

    return ['data' => $games];
}

beforeEach(function () {
    config([
        'services.kadi_api.url' => ROULETTE_API,
        'services.kadi_api.key' => 'site-key',
        'platform.token_launch' => true,
        'platform.deep_link' => true,
    ]);

    $this->rouletteProducts = [['code' => 'classic_black', 'name' => 'Classic Black Roulette']];

    Http::preventStrayRequests();
    Http::fake(fn ($request) => $request->url() === ROULETTE_API.'games'
        ? Http::response(rouletteCatalogue($this->rouletteProducts))
        : Http::response([], 200));
});

test('Roulette shows in the casino as its own tile, with its configured artwork', function () {
    $title = app(GameCatalogue::class)->title('classic_black');

    expect($title)->not->toBeNull()
        ->and($title->game)->toBe('roulette')
        ->and($title->thumbnail)->toBe(asset('games/thumbs/classic_black.webp'))
        ->and($title->typeLabel())->toBe('Roulette')
        ->and(app(Lobby::class)->filter('all', null)->pluck('key'))->toContain('classic_black');

    $this->get(route('casino'))->assertOk()->assertSee('Classic Black Roulette')
        ->assertSee('src="'.asset('games/thumbs/classic_black.webp').'"', false);
    $this->get(route('casino', ['filter' => 'roulette']))->assertOk()->assertSee('Classic Black Roulette')->assertDontSee('Berry Blast');
});

test('the casino page offers a Roulette tab', function () {
    expect(app(Lobby::class)->filters())->toHaveKey('roulette');

    $this->get(route('casino'))->assertSee(route('casino', ['filter' => 'roulette']), false);
});

test('its game page leads back to the casino roulette tab, not to slots', function () {
    $this->get(route('games.show', ['slug' => app(GameCatalogue::class)->title('classic_black')->slug()]))
        ->assertOk()
        ->assertSee('Classic Black Roulette')
        ->assertSee(route('casino', ['filter' => 'roulette']), false)
        ->assertSee('online roulette', false);
});

test('without Roulette in the API there is no tile or tab, and its page is an empty shelf', function () {
    $this->rouletteProducts = [];

    expect(app(GameCatalogue::class)->title('classic_black'))->toBeNull()
        ->and(app(Lobby::class)->filters())->not->toHaveKey('roulette');

    $tabs = fn (string $html) => Str::between($html, 'aria-label="Casino shelves"', '</nav>');
    expect($tabs($this->get(route('casino'))->assertOk()->assertDontSee('Classic Black Roulette')->getContent()))
        ->not->toContain(route('casino', ['filter' => 'roulette']));

    // The side menu links here without reading the catalogue, so the page stays, as an empty shelf.
    $this->get(route('casino', ['filter' => 'roulette']))->assertOk()
        ->assertSee('Nothing to play here right now')
        ->assertDontSee('Classic Black Roulette');
});

test('Roulette products without configured artwork keep a fallback tile', function () {
    $this->rouletteProducts = [['code' => 'new_table', 'name' => 'New Table']];

    $title = app(GameCatalogue::class)->title('new_table');

    expect($title)->not->toBeNull()
        ->and($title->thumbnail)->toBeNull();

    $this->get(route('casino', ['filter' => 'roulette']))->assertOk()
        ->assertSee('class="nt-art-fallback" aria-hidden="true">New Table</span>', false);
});
