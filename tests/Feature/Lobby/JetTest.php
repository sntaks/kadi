<?php

use App\Games\GameCatalogue;
use App\Games\Lobby;
use Illuminate\Support\Facades\Http;

/*
 * Kadi Jet: a casino game from KadiApi, one tile and page per product like Roulette (config/games.php). No artwork yet,
 * so every product gets the plain fallback tile.
 */
const JET_API = 'https://kadi-api.test/api/v1/';

function jetCatalogue(array $products): array
{
    $games = [
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            ['code' => 'slots_berry_blast', 'name' => 'Berry Blast', 'status' => 'active'],
        ]],
    ];

    if ($products !== []) {
        $games[] = ['code' => 'jet', 'name' => 'Kadi Jet', 'launch_url' => 'https://jet.kadi.online/launch', 'status' => 'active', 'sort_order' => 8, 'products' => array_map(
            fn (array $product) => [...$product, 'status' => 'active'],
            $products,
        )];
    }

    return ['data' => $games];
}

beforeEach(function () {
    config([
        'services.kadi_api.url' => JET_API,
        'services.kadi_api.key' => 'site-key',
        'platform.token_launch' => true,
        'platform.deep_link' => true,
    ]);

    $this->jetProducts = [['code' => 'jet_classic', 'name' => 'Kadi Jet Classic']];

    Http::preventStrayRequests();
    Http::fake(fn ($request) => $request->url() === JET_API.'games'
        ? Http::response(jetCatalogue($this->jetProducts))
        : Http::response([], 200));
});

test('Jet shows in the casino and its own tab as a fallback tile', function () {
    $title = app(GameCatalogue::class)->title('jet_classic');

    expect($title)->not->toBeNull()
        ->and($title->game)->toBe('jet')
        ->and($title->thumbnail)->toBeNull()
        ->and($title->typeLabel())->toBe('Jet')
        ->and(app(Lobby::class)->filters())->toHaveKey('jet')
        ->and(app(Lobby::class)->filter('all', null)->pluck('code'))->toContain('jet_classic');

    $this->get(route('casino'))->assertOk()->assertSee(route('casino', ['filter' => 'jet']), false);
    $this->get(route('casino', ['filter' => 'jet']))->assertOk()
        ->assertSee('class="nt-art-fallback" aria-hidden="true">Kadi Jet Classic</span>', false)
        ->assertDontSee('Berry Blast');
});

test('its game page leads back to the casino jet tab, not to slots', function () {
    $this->get(route('games.show', ['slug' => app(GameCatalogue::class)->title('jet_classic')->slug()]))
        ->assertOk()
        ->assertSee('Kadi Jet Classic')
        ->assertSee(route('casino', ['filter' => 'jet']), false);
});

test('without Jet in the API there is no tile or tab, and its page is an empty shelf', function () {
    $this->jetProducts = [];

    expect(app(GameCatalogue::class)->title('jet_classic'))->toBeNull()
        ->and(app(Lobby::class)->filters())->not->toHaveKey('jet');

    $tabs = fn (string $html) => Str::between($html, 'aria-label="Casino shelves"', '</nav>');
    expect($tabs($this->get(route('casino'))->assertOk()->getContent()))
        ->not->toContain(route('casino', ['filter' => 'jet']));

    $this->get(route('casino', ['filter' => 'jet']))->assertOk()
        ->assertSee('Nothing to play here right now')
        ->assertDontSee('Kadi Jet Classic');
});
