<?php

use App\Games\Favourites;
use App\Games\GameCatalogue;
use App\Games\KadiTables;
use App\Games\Lobby;
use App\Livewire\Lobby\GameShow;
use App\Livewire\Welcome;
use App\Models\GameFavourite;
use App\Models\GameLaunch;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
 * Phase 5: the Kadi-first landing, the casino pages and the retired dashboard.
 */

const LOBBY_API = 'https://kadi-api.test/api/v1/';

function lobbyProduct(string $code, string $name): array
{
    return ['code' => $code, 'name' => $name, 'status' => 'active'];
}

/** A catalogue with Kadi, five slots (two collections, one jackpot) and one scratch card. */
function lobbyCatalogue(): array
{
    return ['data' => [
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [
            lobbyProduct('kadi', 'Kadi'),
        ]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            lobbyProduct('slots_dragon_fire_fortune', 'Dragon Fire Fortune'),
            lobbyProduct('slots_crown_of_embers', 'Crown of Embers'),
            lobbyProduct('slots_berry_blast', 'Berry Blast'),
            lobbyProduct('slots_cherry_bomb_deluxe', 'Cherry Bomb Deluxe'),
            lobbyProduct('slots_canyon_quest_jackpot', 'Canyon Quest Jackpot'),
        ]],
        ['code' => 'scratch', 'name' => 'Scratch Cards', 'launch_url' => 'https://scratch.kadi.online', 'status' => 'active', 'sort_order' => 3, 'products' => [
            lobbyProduct('scratch_safari_lotto', 'Safari Lotto'),
        ]],
    ]];
}

beforeEach(function () {
    config([
        'services.kadi_api.url' => LOBBY_API,
        'services.kadi_api.key' => 'site-key',
        'platform.ui_v2' => true,
        'platform.token_launch' => true,
        'platform.deep_link' => true,
    ]);

    // Each test may swap the catalogue or the totals answer: Http::fake stubs stack, so one dispatcher reads these.
    $this->catalogueBody = lobbyCatalogue();
    $this->totals = fn () => Http::response(['single' => ['total' => 96], 'tournaments' => ['total' => 21], 'jackpots' => ['total' => 11]]);

    Http::preventStrayRequests();
    Http::fake(function ($request) {
        return match (true) {
            $request->url() === LOBBY_API.'games' => Http::response($this->catalogueBody),
            str_starts_with($request->url(), 'https://gameapi.kadi.online/') => ($this->totals)(),
            default => Http::response([], 200),
        };
    });
});

// ─── Landing ─────────────────────────────────────────────────────────────────

test('the landing puts the Kadi table first with the real seat counts per mode', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Kadi tables are open')
        ->and($html)->toContain('128 players at the Kadi tables now')
        ->and($html)->toMatch('#<b class="nt-num">96</b><span>in single games</span>#')
        ->and($html)->toMatch('#<b class="nt-num">21</b><span>in tournaments</span>#')
        ->and($html)->toMatch('#<b class="nt-num">11</b><span>chasing jackpots</span>#')
        ->and($html)->toContain('data-shell="app"')
        ->and($html)->not->toContain('noindex');
});

test('with no counts from the game server the landing shows no numbers at all', function () {
    $this->totals = fn () => throw new ConnectionException('down');

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Kadi tables are open')
        ->and($html)->not->toContain('at the Kadi tables now')
        ->and($html)->not->toContain('nt-seats');
});

test('the last good seat counts are kept when the game server fails', function () {
    Cache::forever(KadiTables::LAST_GOOD_KEY, ['single' => 5, 'tournaments' => 1, 'jackpots' => 0, 'total' => 6]);
    $this->totals = fn () => throw new ConnectionException('down');

    expect(app(KadiTables::class)->seats())->toBe(['single' => 5, 'tournaments' => 1, 'jackpots' => 0, 'total' => 6]);
});

test('with the new UI off, home is the old welcome page', function () {
    config(['platform.ui_v2' => false]);

    $this->get(route('home'))->assertOk()->assertDontSee('Kadi tables are open')->assertDontSee('data-shell=', false);
});

test('the casino panel shows themed slot shelves, scratch cards and collections', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('id="casino-title"')
        ->and($html)->toContain('>Featured</button>')
        ->and($html)->toContain('>Dragons &amp; legends</button>')
        ->and($html)->toContain('>Fruit stand</button>')
        ->and($html)->toContain('>Jackpot slots</button>')
        ->and($html)->toContain('Safari Lotto')
        ->and($html)->toContain('href="'.route('casino', ['filter' => 'legends']).'"')
        ->and($html)->toContain('href="'.route('play', ['game' => 'slots', 'title' => 'berry-blast']).'"');
});

test('the casino panel waits until games open inside the site', function () {
    config(['platform.token_launch' => false]);

    $this->get(route('home'))->assertOk()->assertSee('Kadi tables are open')->assertDontSee('id="casino-title"', false);
});

test('"Most played" appears only from the launch log, once enough players have played', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('>Most played</button>', false);

    $players = User::factory()->count(3)->create();
    foreach (['slots_berry_blast', 'slots_crown_of_embers', 'slots_cherry_bomb_deluxe', 'scratch_safari_lotto'] as $product) {
        foreach ($players as $player) {
            GameLaunch::query()->create(['user_id' => $player->id, 'game' => 'slots', 'product' => $product, 'status' => 'launched']);
        }
    }
    Cache::forget('games.most_played');

    $this->get(route('home'))->assertOk()->assertSee('>Most played</button>', false);
});

test('failed launches and too few players never count as most played', function () {
    $players = User::factory()->count(3)->create();
    foreach (['slots_berry_blast', 'slots_crown_of_embers', 'slots_cherry_bomb_deluxe', 'scratch_safari_lotto'] as $product) {
        GameLaunch::query()->create(['user_id' => $players[0]->id, 'game' => 'slots', 'product' => $product, 'status' => 'launched']);
        GameLaunch::query()->create(['user_id' => $players[1]->id, 'game' => 'slots', 'product' => $product, 'status' => 'launched']);
        GameLaunch::query()->create(['user_id' => $players[2]->id, 'game' => 'slots', 'product' => $product, 'status' => 'failed']);
    }

    $this->get(route('home'))->assertOk()->assertDontSee('>Most played</button>', false);
});

test('guests see no hearts; players see their favourites first', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('aria-label="Favourite', false);

    $player = User::factory()->create();
    GameFavourite::query()->create(['user_id' => $player->id, 'product' => 'slots_berry_blast']);

    $html = $this->actingAs($player)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Your favourites')
        ->and($html)->toMatch('#aria-pressed="true" aria-label="Favourite Berry Blast"#');
});

// ─── Favourites ──────────────────────────────────────────────────────────────

test('a player hearts and un-hearts a title', function () {
    $player = User::factory()->create();

    Livewire::actingAs($player)->test(Welcome::class)->call('toggleFavourite', 'slots_berry_blast');
    expect(GameFavourite::where('user_id', $player->id)->pluck('product')->all())->toBe(['slots_berry_blast']);

    Livewire::actingAs($player)->test(Welcome::class)->call('toggleFavourite', 'slots_berry_blast');
    expect(GameFavourite::where('user_id', $player->id)->count())->toBe(0);
});

test('only catalogue titles can be hearted', function () {
    $player = User::factory()->create();

    Livewire::actingAs($player)->test(Welcome::class)->call('toggleFavourite', 'slots_not_a_game');

    expect(GameFavourite::count())->toBe(0);
});

test('a guest tapping a heart is sent to log in', function () {
    Livewire::test(Welcome::class)->call('toggleFavourite', 'slots_berry_blast')->assertRedirect(route('login'));

    expect(GameFavourite::count())->toBe(0);
});

test('favourites stop at the limit, counting only titles still offered', function () {
    config(['games.favourites_limit' => 2]);
    $player = User::factory()->create();
    GameFavourite::query()->create(['user_id' => $player->id, 'product' => 'slots_crown_of_embers']);
    GameFavourite::query()->create(['user_id' => $player->id, 'product' => 'slots_dropped_by_the_api']);

    expect(app(Favourites::class)->toggle($player, 'slots_berry_blast'))->toBeTrue()
        ->and(app(Favourites::class)->toggle($player, 'slots_cherry_bomb_deluxe'))->toBeNull();
});

test('a title the catalogue has dropped can still be removed from favourites', function () {
    $player = User::factory()->create();
    GameFavourite::query()->create(['user_id' => $player->id, 'product' => 'slots_dropped_by_the_api']);

    expect(app(Favourites::class)->toggle($player, 'slots_dropped_by_the_api'))->toBeFalse()
        ->and(GameFavourite::count())->toBe(0);
});

// ─── /casino ─────────────────────────────────────────────────────────────────

test('the casino page lists every title, and each shelf has its own address', function () {
    $this->get(route('casino'))->assertOk()
        ->assertSee('data-shell="app"', false)
        ->assertSee('Berry Blast')->assertSee('Safari Lotto')
        ->assertSee('noindex', false);

    $this->get(route('casino', ['filter' => 'fruit']))->assertOk()
        ->assertSee('Berry Blast')->assertDontSee('Crown of Embers');

    $this->get(route('casino', ['filter' => 'jackpot']))->assertOk()
        ->assertSee('Canyon Quest Jackpot')->assertDontSee('Berry Blast');

    $this->get(route('casino', ['filter' => 'scratch-cards']))->assertOk()
        ->assertSee('Safari Lotto')->assertDontSee('Berry Blast');
});

test('unknown shelves and /casino/all are not pages', function (string $filter) {
    $this->get('/casino/'.$filter)->assertNotFound();
})->with(['all', 'nope']);

test('the casino page does not exist until the new look and in-site games are both on', function (string $flag) {
    config(["platform.{$flag}" => false]);

    $this->get('/casino')->assertNotFound();
})->with(['ui_v2', 'token_launch']);

test('the favourites shelf asks guests to log in and players to heart something', function () {
    $this->get(route('casino', ['filter' => 'favourites']))->assertOk()->assertSee('Keep your favourite games in one place');

    $this->actingAs(User::factory()->create())
        ->get(route('casino', ['filter' => 'favourites']))->assertOk()->assertSee('No favourites yet');
});

// ─── Crawlable game pages ────────────────────────────────────────────────────

test('the slots page groups every slot by collection and links to each game page', function () {
    $html = $this->get(route('games.slots'))->assertOk()->getContent();

    expect($html)->not->toContain('noindex')
        ->and($html)->toContain('Online slots')
        ->and($html)->toContain('Dragons &amp; legends')
        ->and($html)->toContain('href="'.route('games.show', ['slug' => 'berry-blast']).'"')
        ->and($html)->not->toContain('Safari Lotto')
        ->and($html)->toContain('"@type":"ItemList"');
});

test('the scratch cards page lists the scratch cards', function () {
    $this->get(route('games.scratch'))->assertOk()->assertSee('Safari Lotto')->assertDontSee('Berry Blast');
});

test('a game page describes the title, links to play and marks it up', function () {
    $html = $this->get(route('games.show', ['slug' => 'canyon-quest-jackpot']))->assertOk()->getContent();

    expect($html)->toContain('<h1>Canyon Quest Jackpot</h1>')
        ->and($html)->toContain('is an online slot with a jackpot from the Untamed collection')
        ->and($html)->toContain('href="'.route('play', ['game' => 'slots', 'title' => 'canyon-quest-jackpot']).'"')
        ->and($html)->toContain('Log in to play')
        ->and($html)->toContain('"@type":"VideoGame"')
        ->and($html)->toContain('"@type":"BreadcrumbList"');
});

test('a game page says play opens soon while games do not open inside the site', function () {
    config(['platform.token_launch' => false]);

    $this->get(route('games.show', ['slug' => 'berry-blast']))->assertOk()
        ->assertSee('Opens for play here soon')
        ->assertDontSee(route('play', ['game' => 'slots', 'title' => 'berry-blast']), false);
});

test('only catalogue titles have a game page', function () {
    $this->get('/games/not-a-game')->assertNotFound();
});

test('game history and search keep their own addresses', function () {
    $this->actingAs(User::factory()->create())->get('/games/history')->assertRedirect(route('activity'));
    $this->get('/games/search')->assertOk()->assertJsonStructure(['data']);
});

test('the game pages do not exist until crawlable pages are on', function () {
    config(['platform.deep_link' => false]);

    $this->get('/slots')->assertNotFound();
    $this->get('/scratch-cards')->assertNotFound();
    $this->get('/games/berry-blast')->assertNotFound();
});

test('a player can heart a title from its game page', function () {
    $player = User::factory()->create();

    Livewire::actingAs($player)->test(GameShow::class, ['slug' => 'berry-blast'])
        ->call('toggleFavourite', 'slots_berry_blast')
        ->assertSee('In your favourites');
});

// ─── Dashboard, menu and sitemap ─────────────────────────────────────────────

test('with the new UI on, the dashboard sends players home', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('home'));
});

test('the old dashboard makes no claims about players or dealers', function () {
    config(['platform.ui_v2' => false]);

    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain(' Playing<')->and($html)->not->toContain('playing now')->and($html)->not->toContain('Live Dealer');
});

test('a 200 without all three totals keeps the last good seat counts', function (mixed $body) {
    Cache::forever(KadiTables::LAST_GOOD_KEY, ['single' => 5, 'tournaments' => 1, 'jackpots' => 0, 'total' => 6]);
    $this->totals = fn () => Http::response($body);

    expect(app(KadiTables::class)->seats())->toBe(['single' => 5, 'tournaments' => 1, 'jackpots' => 0, 'total' => 6]);
})->with([
    'an error object' => [['error' => 'db down']],
    'one mode missing' => [['single' => ['total' => 3], 'tournaments' => ['total' => 1]]],
    'a negative total' => [['single' => ['total' => -1], 'tournaments' => ['total' => 1], 'jackpots' => ['total' => 0]]],
]);

test('a broken totals URL hides the counts instead of breaking the page', function () {
    $this->totals = fn () => throw new InvalidArgumentException('bad url');

    $this->get(route('home'))->assertOk()->assertSee('Kadi tables are open')->assertDontSee('at the Kadi tables now');
});

test('the last good seat counts can be read without calling the game server', function () {
    app(KadiTables::class)->seats();
    $this->totals = fn () => throw new RuntimeException('called the game server');

    expect(app(KadiTables::class)->lastGood()['total'])->toBe(128);
});

test('game pages only exist for clean, unreserved, first-come slugs', function () {
    $this->catalogueBody = ['data' => [
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            lobbyProduct('slots_lucky_sevens', 'Lucky Sevens'),
            lobbyProduct('slots_history', 'History'),
            lobbyProduct('slots_Mega.Win', 'Mega Win'),
        ]],
        ['code' => 'scratch', 'name' => 'Scratch Cards', 'launch_url' => 'https://scratch.kadi.online', 'status' => 'active', 'sort_order' => 3, 'products' => [
            lobbyProduct('scratch_lucky_sevens', 'Lucky Sevens Scratch'),
        ]],
    ]];
    $catalogue = app(GameCatalogue::class);
    $catalogue->refresh();
    $urls = $catalogue->titles()->mapWithKeys(fn ($title) => [$title->code => $catalogue->pageUrl($title)]);

    expect($urls->all())->toBe([
        'slots_lucky_sevens' => route('games.show', ['slug' => 'lucky-sevens']),
        'slots_history' => null,
        'slots_Mega.Win' => null,
        'scratch_lucky_sevens' => null,
    ]);
});

test('a collection named like a built-in shelf is never a shelf', function () {
    config(['games.collections.jackpot' => ['label' => 'Clash', 'products' => ['slots_berry_blast']]]);

    $lobby = app(Lobby::class);

    expect($lobby->filters()['jackpot'])->toBe('Jackpot slots')
        ->and(collect($lobby->themes())->pluck('label'))->not->toContain('Clash');
});

test('no collection in config is named like a built-in shelf', function () {
    expect(array_intersect_key(config('games.collections'), Lobby::BUILT_IN_FILTERS + ['featured' => 1, 'most-played' => 1]))->toBe([]);
});

test('the menu gets a Casino tab and shelf links', function () {
    $html = $this->get(route('casino', ['filter' => 'slots']))->assertOk()->getContent();

    expect($html)->toMatch('#<a href="'.preg_quote(route('casino'), '#').'"\s*wire:navigate[^>]*>\s*<svg#')
        ->and($html)->toMatch('#<a href="'.preg_quote(route('casino', ['filter' => 'slots']), '#').'"\s+class="nt-nav-item"[^>]*aria-current="page"#');
});

test('the sitemap lists the game pages while they are on', function () {
    $realPublic = public_path();
    $public = storage_path('framework/testing/sitemap-'.uniqid());
    File::ensureDirectoryExists($public);
    app()->usePublicPath($public);

    $this->artisan('sitemap:generate')->assertSuccessful()->run();
    $xml = File::get($public.'/sitemap.xml');

    app()->usePublicPath($realPublic);
    File::deleteDirectory($public);

    expect($xml)->toContain(route('games.slots'))
        ->and($xml)->toContain(route('games.scratch'))
        ->and($xml)->toContain(route('games.show', ['slug' => 'berry-blast']))
        ->and($xml)->toContain(route('games.show', ['slug' => 'safari-lotto']));
});
