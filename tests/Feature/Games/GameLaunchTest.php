<?php

use App\Account\ResponsiblePlay;
use App\Games\GameCatalogue;
use App\Jobs\RevokeGameSessions;
use App\Livewire\Games\Play;
use App\Models\GameLaunch;
use App\Models\User;
use App\Services\KadiApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

const LAUNCH_API = 'https://kadi-api.test/api/v1/';
const LAUNCH_TOKEN = 'Xq3-secret-launch-token-0123456789abcdefghij';

/** `GET /games` with Kadi, two slots and one scratch card (the API's codes carry the game prefix). */
function launchCatalogue(string $slotsUrl = 'https://slots.kadi.online'): array
{
    $p = fn (string $code, string $game, string $name) => ['code' => $code, 'game' => $game, 'name' => $name, 'status' => 'active'];

    return ['data' => [
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [$p('kadi', 'kadi', 'Kadi')]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => $slotsUrl, 'status' => 'active', 'sort_order' => 2, 'products' => [
            $p('slots_berry_blast', 'slots', 'Berry Blast'), $p('slots_dragon_fire_fortune', 'slots', 'Dragon Fire Fortune'),
        ]],
        ['code' => 'scratch', 'name' => 'Scratch Cards', 'launch_url' => 'https://scratch.kadi.online', 'status' => 'active', 'sort_order' => 3, 'products' => [
            $p('scratch_safari', 'scratch', 'Safari Lotto'),
        ]],
    ]];
}

function fakeLaunchApi(mixed $launch = null, ?array $catalogue = null, ?array $exclusion = null): void
{
    Http::fake([
        LAUNCH_API.'games' => Http::response($catalogue ?? launchCatalogue()),
        LAUNCH_API.'customers/*/responsible-gambling' => Http::response(['success' => true, 'data' => [
            'status' => 1, 'can_play' => $exclusion === null, 'exclusion' => $exclusion, 'deposit_limits' => [], 'deposit_remaining' => null,
        ]]),
        LAUNCH_API.'customers/*/launch-tokens' => $launch ?? Http::response(['success' => true, 'data' => [
            'token' => LAUNCH_TOKEN, 'expires_at' => '2026-11-03T12:02:00+03:00', 'launch_url' => 'https://slots.kadi.online/launch',
        ]], 201),
        LAUNCH_API.'customers/*/player-tokens/revoke' => Http::response(['success' => true, 'data' => ['revoked' => 2]]),
    ]);
}

/** POST a launch and return the hand-off URL it redirects to. */
function launchFor(TestCase $test, string $game, array $body = []): string
{
    $response = $test->post("/play/{$game}", $body)->assertStatus(303);
    $location = $response->headers->get('Location');
    expect($location)->toMatch('#/play/'.$game.'/open/[A-Za-z0-9]{40}$#');

    return $location;
}

function player(array $attributes = []): User
{
    return User::factory()->create($attributes + ['linked_id' => 4242, 'account_no' => 'KK-'.strtoupper(uniqid())]);
}

beforeEach(function () {
    config([
        'platform.token_launch' => true,
        'services.kadi_api.url' => LAUNCH_API,
        'services.kadi_api.play_url' => 'https://play.kadi.online',
        'games.games.kadi.frame' => false,
    ]);
});

// ─── Access ─────────────────────────────────────────────────────────────────

test('play routes do not exist while the launcher switch is off', function () {
    config(['platform.token_launch' => false]);
    fakeLaunchApi();

    $this->actingAs(player())->get('/play/kadi')->assertNotFound();
    $this->actingAs(player())->post('/play/kadi')->assertNotFound();
    $this->actingAs(player())->get('/play/kadi/open/'.str_repeat('a', 40))->assertNotFound();
});

test('guests are sent to log in and unverified players to verify', function () {
    fakeLaunchApi();

    $this->get('/play/slots/berry-blast')->assertRedirect(route('login'));
    $this->post('/play/kadi')->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get('/play/slots/berry-blast')->assertRedirect(route('verification.notice'));
});

test('unknown games, unknown or foreign titles and stray title segments are not found', function (string $url) {
    fakeLaunchApi();

    $this->actingAs(player())->get($url)->assertNotFound();
})->with([
    'unknown game' => ['/play/poker'],
    'slots without a title' => ['/play/slots'],
    'unknown slot' => ['/play/slots/not-a-slot'],
    'scratch card under slots' => ['/play/slots/safari'],
    'kadi with a title' => ['/play/kadi/berry-blast'],
]);

// ─── The play page ──────────────────────────────────────────────────────────

test('the play page frames the game and posts the launch into the frame without issuing a token', function () {
    fakeLaunchApi();

    $html = $this->actingAs(player())->get('/play/slots/berry-blast')->assertOk()->getContent();

    expect($html)->toContain('<iframe name="game-frame"')
        ->toContain('action="'.route('play.launch', ['game' => 'slots']).'"')
        ->toContain('target="game-frame"')
        ->toContain('name="product" value="slots_berry_blast"')
        ->toContain('referrerpolicy="no-referrer"')
        // The frame has no src, so fullscreen must name the game servers or games cannot use it.
        ->toMatch('/allow="fullscreen [^";]*https:\/\/slots\.kadi\.online[^"]*"/')
        ->toContain('Dragon Fire Fortune') // more like this
        ->not->toContain(LAUNCH_TOKEN);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'launch-tokens'));
});

test('only the game servers may message the play page', function () {
    fakeLaunchApi(catalogue: launchCatalogue('https://slots.kadi.online'));

    $origins = Livewire::actingAs(player())->test(Play::class, ['game' => 'scratch', 'title' => 'safari'])->viewData('origins');

    expect($origins)
        ->toEqualCanonicalizing(['https://play.kadi.online', 'https://slots.kadi.online', 'https://scratch.kadi.online']);
});

test('Kadi, not framed yet, opens in the page itself', function () {
    fakeLaunchApi();

    $html = $this->actingAs(player())->get('/play/kadi')->assertOk()->getContent();

    expect($html)->toContain('target="_self"')->not->toContain('<iframe name="game-frame"');
});

// ─── Launching Kadi (ggid) ──────────────────────────────────────────────────

test('Kadi keeps its ggid form: account_no posted to the play url, without a launch token', function () {
    fakeLaunchApi();
    $user = player(['account_no' => 'KK-GGID-7']);

    $this->actingAs($user);
    $open = $this->get(launchFor($this, 'kadi'))->assertOk();
    expect($open->headers->get('Cache-Control'))->toContain('no-store');
    $open->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('action="https://play.kadi.online"', false)
        ->assertSee('<input type="hidden" name="ggid" value="KK-GGID-7">', false);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'launch-tokens'));
    expect(GameLaunch::query()->sole())->toMatchArray(['user_id' => $user->id, 'game' => 'kadi', 'product' => null, 'status' => 'launched']);
});

test('Kadi still launches when the catalogue is unavailable', function () {
    Http::fake([LAUNCH_API.'games' => Http::response([], 500)]);

    $this->actingAs(player());
    $this->get(launchFor($this, 'kadi'))->assertSee('name="ggid"', false);
});

test('a player without an account number cannot launch Kadi', function () {
    fakeLaunchApi();

    $this->actingAs(player(['account_no' => '']));
    $this->get(launchFor($this, 'kadi'))->assertStatus(422)->assertSee('still being set up')->assertDontSee('name="ggid"', false);

    expect(GameLaunch::query()->sole()->error)->toBe('not_linked');
});

// ─── Launching with a token ─────────────────────────────────────────────────

test('a slot launches with a single-use token posted to the game with the title', function () {
    fakeLaunchApi();
    $user = player();

    $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '197.248.10.20']);
    $open = launchFor($this, 'slots', ['product' => 'slots_berry_blast']);

    // The customer id is encrypted with a random IV, so only its presence is checked.
    Http::assertSent(fn (Request $request) => preg_match('#/customers/[^/]+/launch-tokens$#', $request->url()) === 1
        && $request['game'] === 'slots' && $request['ip'] === '197.248.10.20'
        && $request->hasHeader('x-api-key'));

    $this->get($open)->assertOk()
        ->assertSee('action="https://slots.kadi.online/launch"', false)
        ->assertSee('name="token" value="'.LAUNCH_TOKEN.'"', false)
        ->assertSee('name="product" value="slots_berry_blast"', false);

    // The log keeps the title's key, so "Most played" survives a prefix the API adds or drops.
    expect(GameLaunch::query()->sole())->toMatchArray(['game' => 'slots', 'product' => 'berry_blast', 'status' => 'launched', 'error' => null]);
});

test('a play page opened before the API prefixed its codes still launches with the API code', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertOk()
        ->assertSee('name="product" value="slots_berry_blast"', false);
});

test('slot and scratch launches also post the encrypted customer id as account_id', function (string $game, string $product) {
    fakeLaunchApi();
    $this->actingAs(player(['linked_id' => 4242]));

    $html = $this->get(launchFor($this, $game, ['product' => $product]))->assertOk()->getContent();

    // encrypt_account_id(): random IV and a signature, so it is verified and decrypted rather than compared.
    expect(preg_match('/name="account_id" value="([^"]+)"/', $html, $m))->toBe(1)
        ->and($m[1])->toStartWith('v1.')
        ->and($m[1])->not->toContain('4242')
        ->and(decryptAccountId($m[1]))->toBe('4242');
})->with([
    'slot' => ['slots', 'berry_blast'],
    'scratch card' => ['scratch', 'safari'],
]);

test('without the account_id keys a slot never launches and no launch token is spent', function () {
    config(['games.launch.account_id_key' => null]);
    fakeLaunchApi();
    $this->actingAs(player());

    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertStatus(422)
        ->assertSee("This game can't be opened right now")->assertDontSee('name="account_id"', false);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/launch-tokens'));
    expect(GameLaunch::query()->sole()->error)->toBe('not_configured');
});

test('Kadi keeps its ggid form without an account_id', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $this->get(launchFor($this, 'kadi'))->assertOk()->assertSee('name="ggid"', false)->assertDontSee('name="account_id"', false);
});

test('the hand-off works once: Back or refresh never re-posts a launch', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $open = launchFor($this, 'slots', ['product' => 'berry_blast']);
    $this->get($open)->assertSee(LAUNCH_TOKEN, false);

    $this->get($open)->assertStatus(410)->assertSee('This game was closed')->assertSee('kadi-launch-used', false)->assertDontSee(LAUNCH_TOKEN, false);
});

test('a hand-off older than a launch token is never posted', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $open = launchFor($this, 'slots', ['product' => 'berry_blast']);
    $this->travel(121)->seconds();

    $this->get($open)->assertStatus(410)->assertDontSee(LAUNCH_TOKEN, false);
});

test('a hand-off opens only for its own game and its own player', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $wrongGame = str_replace('/play/slots/', '/play/scratch/', launchFor($this, 'slots', ['product' => 'berry_blast']));
    $this->get($wrongGame)->assertStatus(410)->assertDontSee(LAUNCH_TOKEN, false);

    $open = launchFor($this, 'slots', ['product' => 'berry_blast']);
    $this->actingAs(player())->get($open)->assertStatus(410)->assertDontSee(LAUNCH_TOKEN, false);
});

test('overlapping launches each keep their own hand-off', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $slot = launchFor($this, 'slots', ['product' => 'berry_blast']);
    $card = launchFor($this, 'scratch', ['product' => 'safari']);

    $this->get($card)->assertOk()->assertSee('name="product" value="scratch_safari"', false);
    $this->get($slot)->assertOk()->assertSee('name="product" value="slots_berry_blast"', false);
});

test('a hand-off is not a page: anything else in the frame is reported as an error by the play page', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $this->get(launchFor($this, 'kadi'))->assertSee('<main data-handoff>', false);
});

test('forged titles are refused before any token is issued', function (array $body) {
    fakeLaunchApi();

    $this->actingAs(player())->post('/play/slots', $body)->assertNotFound();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'launch-tokens'));
    expect(GameLaunch::query()->count())->toBe(0);
})->with([
    'no title' => [[]],
    'unknown title' => [['product' => 'free_money']],
    'another game\'s title' => [['product' => 'safari']],
    'not a string' => [['product' => ['berry_blast']]],
]);

test('API refusals become plain messages and are recorded', function (mixed $response, string $reason, string $message) {
    fakeLaunchApi($response);

    $this->actingAs(player());
    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertStatus(422)->assertSee($message)->assertSee('kadi-launch-error', false);

    expect(GameLaunch::query()->sole())->toMatchArray(['status' => 'failed', 'error' => $reason]);
})->with([
    'player inactive' => [fn () => Http::response(['success' => false, 'code' => 'player_inactive', 'message' => 'This player cannot play'], 403), 'player_inactive', 'Contact support'],
    'game closed' => [fn () => Http::response(['success' => false, 'code' => 'game_unavailable', 'message' => 'This game cannot be launched now'], 403), 'game_unavailable', 'closed right now'],
    'game not found' => [fn () => Http::response(['success' => false, 'message' => 'Game not found'], 404), 'game_unavailable', 'closed right now'],
    'customer not found' => [fn () => Http::response(['success' => false, 'message' => 'Customer not found'], 404), 'not_linked', 'still being set up'],
    'API rate limit' => [fn () => Http::response(['message' => 'Too Many Attempts.'], 429), 'rate_limited', 'Wait a minute'],
    'server error' => [fn () => Http::response(['message' => 'boom'], 500), 'unreachable', "couldn't reach the game"],
    'unreachable' => [fn () => Http::failedConnection(), 'unreachable', "couldn't reach the game"],
    'incomplete reply' => [fn () => Http::response(['data' => ['token' => LAUNCH_TOKEN]], 201), 'unreachable', "couldn't reach the game"],
    'foreign launch url' => [fn () => Http::response(['data' => ['token' => LAUNCH_TOKEN, 'launch_url' => 'https://evil.example/launch']], 201), 'bad_launch_url', "can't be opened safely"],
]);

test('a player not linked to KadiApi is refused without an API call', function () {
    fakeLaunchApi();

    $this->actingAs(player(['linked_id' => null]));
    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertSee('still being set up');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'launch-tokens'));
});

test('launch tokens never reach the logs, the database, the session, a URL or the cache in readable form', function () {
    $logged = [];
    Log::listen(function ($message) use (&$logged) {
        $logged[] = $message->message.json_encode($message->context);
    });
    fakeLaunchApi();

    $this->actingAs(player());
    $open = launchFor($this, 'slots', ['product' => 'berry_blast']);

    expect($open)->not->toContain(LAUNCH_TOKEN)
        ->and(json_encode(session()->all()))->not->toContain(LAUNCH_TOKEN)
        ->and(serialize(Cache::get('game-handoff:'.basename($open))))->not->toContain(LAUNCH_TOKEN);

    $this->get($open);

    // Also a refused launch with the token in the reply.
    fakeLaunchApi(Http::response(['data' => ['token' => LAUNCH_TOKEN, 'launch_url' => 'https://evil.example']], 201));
    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']));

    expect(implode("\n", $logged))->not->toContain(LAUNCH_TOKEN)
        ->and(GameLaunch::query()->get()->toJson())->not->toContain(LAUNCH_TOKEN);
});

test('players are limited to a few launches a minute', function () {
    config(['games.launch.per_minute' => 2]);
    fakeLaunchApi();
    $this->actingAs(player());

    $this->post('/play/slots', ['product' => 'berry_blast'])->assertStatus(303);
    $this->post('/play/slots', ['product' => 'berry_blast'])->assertStatus(303);
    $this->post('/play/slots', ['product' => 'berry_blast'])->assertStatus(429)->assertSee('Wait a minute');

    Http::assertSentCount(3); // the catalogue and two launch tokens
});

// ─── Breaks from play ───────────────────────────────────────────────────────

test('a player on a break cannot open Kadi, whose form never asks KadiApi', function () {
    fakeLaunchApi(exclusion: ['type' => 'cool_off', 'ends_at' => now()->addDay()->toIso8601String()]);
    $this->actingAs(player());

    $this->get(launchFor($this, 'kadi'))->assertStatus(422)->assertSee('taking a break from play', false);

    expect(GameLaunch::query()->latest('id')->first()->error)->toBe('on_break');
});

test('token games leave the break check to KadiApi, with no extra lookup', function () {
    fakeLaunchApi();
    $this->actingAs(player());

    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertOk();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'responsible-gambling'));
});

test('a break that has ended no longer blocks Kadi', function () {
    fakeLaunchApi(exclusion: ['type' => 'cool_off', 'ends_at' => now()->subMinute()->toIso8601String()]);
    $this->actingAs(player());

    $this->get(launchFor($this, 'kadi'))->assertOk();
});

test('the API refusing a launch for a break shows the break message', function () {
    fakeLaunchApi(Http::response(['success' => false, 'code' => 'player_excluded', 'message' => 'This player is taking a break from play'], 403));
    $this->actingAs(player());

    $this->get(launchFor($this, 'slots', ['product' => 'berry_blast']))->assertStatus(422)->assertSee('taking a break from play', false);
});

test('Play Kadi is disabled during a break', function () {
    config(['platform.token_launch' => false]);
    fakeLaunchApi(exclusion: ['type' => 'self_exclusion', 'ends_at' => now()->addMonths(6)->toIso8601String()]);
    $this->actingAs($user = player());
    app(ResponsiblePlay::class)->state($user); // a page or launch already learned of the break

    expect(Blade::render('<x-play-kadi>Play</x-play-kadi>'))->toContain('disabled data-play-kadi')->not->toContain('ggid');
});

// ─── Logout ─────────────────────────────────────────────────────────────────

test('logging out ends the player\'s game sessions', function () {
    Queue::fake();
    $user = player(['linked_id' => 5150]);

    $this->actingAs($user)->post(route('logout'));

    Queue::assertPushed(RevokeGameSessions::class, fn (RevokeGameSessions $job) => $job->customerId === 5150 && $job->game === null);
});

test('logging out does nothing for games while the launcher and betting are off or the player is not linked', function (bool $launcher, ?int $linked) {
    config(['platform.token_launch' => $launcher, 'platform.sports_betting' => $launcher]);
    Queue::fake();

    $this->actingAs(player(['linked_id' => $linked]))->post(route('logout'));

    Queue::assertNotPushed(RevokeGameSessions::class);
})->with([
    'launcher off' => [false, 5150],
    'not linked' => [true, null],
]);

test('the revoke job calls the API, and a vanished customer is not an error', function () {
    fakeLaunchApi();
    (new RevokeGameSessions(5150))->handle(app(KadiApiService::class));

    Http::assertSent(fn (Request $request) => preg_match('#/customers/[^/]+/player-tokens/revoke$#', $request->url()) === 1 && $request->method() === 'POST');

    Http::fake(['*' => Http::response(['message' => 'Customer not found'], 404)]);
    (new RevokeGameSessions(5150))->handle(app(KadiApiService::class));
    Http::assertSentCount(1);
});

// ─── Slugs ──────────────────────────────────────────────────────────────────

test('titles have readable slugs made from their product codes, without the game prefix', function () {
    fakeLaunchApi();
    $catalogue = app(GameCatalogue::class);

    expect($catalogue->title('slots_dragon_fire_fortune')->slug())->toBe('dragon-fire-fortune')
        ->and($catalogue->title('scratch_safari')->slug())->toBe('safari')
        ->and($catalogue->title('safari')->code)->toBe('scratch_safari')
        ->and($catalogue->game('slots')->titleBySlug('berry-blast')->code)->toBe('slots_berry_blast')
        ->and($catalogue->game('slots')->titleBySlug('slots-berry-blast'))->toBeNull();
});
