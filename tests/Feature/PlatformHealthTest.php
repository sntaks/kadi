<?php

use App\Games\GameCatalogue;
use App\Models\GameLaunch;
use App\Models\User;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\WagerOp;
use App\Support\PlatformHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * Phase 12: platform:health reports what needs a person to Sentry, one issue per check (docs/platform-health.md).
 */

beforeEach(function () {
    // A working catalogue API: the check reads the catalogue itself before judging how old it is.
    config(['services.kadi_api.url' => 'https://api.test/api/v1/', 'services.kadi_api.key' => 'site-key']);
    $this->catalogueUp = true;
    Http::fake(['api.test/api/v1/games*' => fn () => $this->catalogueUp
        ? Http::response(['data' => [['code' => 'slots', 'name' => 'Slots', 'status' => 'active', 'sort_order' => 2, 'launch_url' => 'https://slots.kadi.online/play', 'products' => []]]])
        : Http::response([], 503)]);
});

function healthLaunches(int $count, string $status, ?string $error = null): void
{
    $user = User::factory()->create();

    foreach (range(1, $count) as $i) {
        GameLaunch::create(['user_id' => $user->id, 'game' => 'slots', 'status' => $status, 'error' => $error]);
    }
}

test('a quiet, healthy platform reports nothing', function () {
    healthLaunches(10, 'launched');

    expect(array_filter(app(PlatformHealth::class)->check()))->toBe([]);
    $this->artisan('platform:health')->assertSuccessful();
});

test('launches failing on the platform side are reported, with the reasons and no player', function () {
    healthLaunches(10, 'launched');
    healthLaunches(4, 'failed', 'unreachable');
    healthLaunches(2, 'failed', 'rate_limited');

    $problem = app(PlatformHealth::class)->check()['game_launches'];

    expect($problem['message'])->toContain('6 of 16')->toContain('unreachable: 4')->toContain('rate_limited: 2')
        ->and($problem['context'])->toMatchArray(['failed' => 6, 'total' => 16])
        ->and(json_encode($problem))->not->toContain('user');

    $this->artisan('platform:health')->assertFailed();
});

test('players on a break or not yet linked are not a platform failure, and a few failures are noise', function () {
    healthLaunches(2, 'launched');
    healthLaunches(10, 'failed', 'on_break');
    healthLaunches(10, 'failed', 'not_linked');
    healthLaunches(4, 'failed', 'unreachable');

    expect(app(PlatformHealth::class)->check()['game_launches'])->toBeNull();
});

test('a settlement op that failed or waits too long is reported', function () {
    $api = wagerApi();
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    betOn($api->player(), [[$fixture, 'home']]);
    $api->next('settle', [422, 'payout_above_limit']);
    recordScore($fixture, 1, 0);

    expect(WagerOp::sole()->status)->toBe(WagerOp::FAILED)
        ->and(app(PlatformHealth::class)->check()['wager_ops']['context'])->toMatchArray(['ops' => 1, 'failed' => 1]);
});

test('a placement still unconfirmed after the alert window is reported', function () {
    $api = wagerApi();
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $bet = betOn($api->player(), [[$fixture, 'home']]);
    Bet::query()->whereKey($bet->id)->update(['status' => 'pending', 'placed_at' => now('UTC')->subHour()]);

    expect(app(PlatformHealth::class)->check()['wager_placements']['context'])->toBe(['bets' => 1, 'jackpot_entries' => 0]);
});

test('a quiet night or a cleared cache is not an outage: the check asks the catalogue itself', function () {
    Cache::flush();

    expect(app(PlatformHealth::class)->check()['game_catalogue'])->toBeNull()
        ->and(Cache::get(GameCatalogue::FETCHED_AT_KEY))->toBe(now()->getTimestamp());
});

test('a catalogue the Kadi API has not answered for too long is reported', function () {
    config(['kadi.health.catalogue_stale_minutes' => 30]);
    $this->catalogueUp = false;

    Cache::forever(GameCatalogue::FETCHED_AT_KEY, now()->subMinutes(10)->getTimestamp());
    expect(app(PlatformHealth::class)->check()['game_catalogue'])->toBeNull();

    Cache::forget(GameCatalogue::FRESH_KEY);
    Cache::forever(GameCatalogue::FETCHED_AT_KEY, now()->subMinutes(45)->getTimestamp());
    expect(app(PlatformHealth::class)->check()['game_catalogue']['message'])->toContain('45 minutes ago');

    config(['platform.token_launch' => false]);
    expect(app(PlatformHealth::class)->check()['game_catalogue'])->toBeNull();
});

test('the check runs every five minutes on one server', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'platform:health'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *')->and($event->onOneServer)->toBeTrue();
});

test('stuck settlements are counted in full, not capped at a page of rows', function () {
    $api = wagerApi();
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    betOn($api->player(), [[$fixture, 'home']]);
    $api->next('settle', [422, 'payout_above_limit']);
    recordScore($fixture, 1, 0);
    WagerOp::query()->update(['created_at' => now('UTC')->subMinutes(40)]);

    expect(app(PlatformHealth::class)->check()['wager_ops']['context'])->toMatchArray(['ops' => 1, 'failed' => 1, 'oldest_minutes' => 40]);
});

test('the first run after a deploy checks the API even while an older cached copy is still fresh', function () {
    // A copy cached before the timestamp existed: reading it alone would never record an answer.
    Cache::put(GameCatalogue::FRESH_KEY, [['code' => 'slots', 'name' => 'Slots', 'status' => 'active', 'launch_url' => 'https://slots.kadi.online/play', 'products' => []]], 300);
    Cache::forget(GameCatalogue::FETCHED_AT_KEY);

    expect(app(PlatformHealth::class)->check()['game_catalogue'])->toBeNull()
        ->and(Cache::get(GameCatalogue::FETCHED_AT_KEY))->toBe(now()->getTimestamp());

    Http::assertSent(fn ($request) => str_contains($request->url(), '/games'));
});

test('with no answer on record and the API down, the problem is reported', function () {
    $this->catalogueUp = false;
    Cache::put(GameCatalogue::FRESH_KEY, [['code' => 'slots', 'name' => 'Slots', 'status' => 'active', 'launch_url' => 'https://slots.kadi.online/play', 'products' => []]], 300);
    Cache::forget(GameCatalogue::FETCHED_AT_KEY);

    expect(app(PlatformHealth::class)->check()['game_catalogue']['message'])->toContain('could not be fetched');
});
