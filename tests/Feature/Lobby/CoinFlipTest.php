<?php

use App\Games\GameCatalogue;
use App\Games\Lobby;
use Illuminate\Support\Facades\Http;

/*
 * Coin Flip: a casino game from KadiApi, one tile and page per product like slots (config/games.php). No artwork
 * yet, so the plain fallback tile. More products appear as the API adds them.
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

    $this->coinProducts = [['code' => 'coin_flip', 'name' => 'Coin Flip']];

    Http::preventStrayRequests();
    Http::fake(fn ($request) => $request->url() === COIN_API.'games'
        ? Http::response(coinCatalogue($this->coinProducts))
        : Http::response([], 200));
});

test('Coin Flip shows in the casino as its own tile, with the fallback art until artwork arrives', function () {
    $title = app(GameCatalogue::class)->title('coin_flip');

    expect($title)->not->toBeNull()
        ->and($title->game)->toBe('coinflip')
        ->and($title->thumbnail)->toBeNull()
        ->and(app(Lobby::class)->filter('all', null)->pluck('key'))->toContain('coin_flip');

    $this->get(route('casino'))->assertOk()->assertSee('Coin Flip');
    $this->get(route('casino', ['filter' => 'coin-flip']))->assertOk()->assertSee('Coin Flip')->assertDontSee('Berry Blast');
});

// The site menu never reads the catalogue (SiteShellTest), so Coin flip is a tab on the casino page, not a menu link.
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

test('without Coin Flip in the API there is no tile, tab, menu link or page', function () {
    $this->coinProducts = [];

    expect(app(GameCatalogue::class)->title('coin_flip'))->toBeNull()
        ->and(app(Lobby::class)->filters())->not->toHaveKey('coin-flip');

    $this->get(route('casino'))->assertOk()->assertDontSee('Coin Flip')->assertDontSee(route('casino', ['filter' => 'coin-flip']), false);
    $this->get(route('casino', ['filter' => 'coin-flip']))->assertNotFound();
});
