<?php

use App\Livewire\Profile\Show;
use App\Models\User;
use App\Services\WalletBalanceFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const LAR_API = 'https://api.kadi-kings.co.ke/api/v1/';
const LAR_GAME_API = 'https://gameapi.kadi.online/*';

/** Calls about a player (balance, profile). The landing may read the game catalogue, which is not about anyone. */
function kadiApiCalls(): Closure
{
    return fn (Request $request) => str_starts_with($request->url(), LAR_API.'customers');
}

// ─── Wallet balance ─────────────────────────────────────────────────────────

test('two cold loads at once cost one KadiApi call', function () {
    Http::fake([LAR_API.'customers/*' => Http::response(['data' => ['balance' => 40]])]);
    $user = User::factory()->create(['linked_id' => 7104]);
    $fetcher = app(WalletBalanceFetcher::class);

    // The second caller reaches the lock after the first has filled the cache.
    expect($fetcher->fetch($user))->toBe(['balance' => 40.0, 'fresh' => true])
        ->and($fetcher->fetch($user))->toBe(['balance' => 40.0, 'fresh' => false]);

    Http::assertSentCount(1);
});

// ─── Profile ────────────────────────────────────────────────────────────────

test('profile with a cold cache shows stat skeletons, disables Save and calls nothing before first paint', function () {
    Http::fake();
    $user = User::factory()->create(['linked_id' => 7201]);

    Livewire::actingAs($user)->test(Show::class)
        ->assertSet('needsLoad', true)
        ->assertSeeHtml('wire:init="loadCustomer"')
        ->assertSeeHtml('data-test="profile-stat-skeleton"')
        ->assertSeeHtmlInOrder(['<button type="submit" disabled', 'data-test="profile-save"']);

    Http::assertNothingSent();
});

test('profile loadCustomer fills the stats and enables Save', function () {
    Http::fake([LAR_API.'customers/*' => Http::response(['data' => [
        'balance' => 10, 'id_no' => '12345678', 'deposits' => 1500, 'withdraws' => 200, 'single_played' => 7, 'competition_played' => 2,
    ]])]);
    $user = User::factory()->create(['linked_id' => 7202]);

    Livewire::actingAs($user)->test(Show::class)
        ->call('loadCustomer')
        ->assertSet('needsLoad', false)
        ->assertSet('idNo', '12345678')
        ->assertSee('KES 1,500.00')
        ->assertDontSeeHtml('data-test="profile-stat-skeleton"')
        ->assertDontSeeHtml('<button type="submit" disabled');
});

test('saving a profile whose KadiApi data never loaded does not send a blank ID number', function () {
    Http::fake([
        LAR_API.'customers/*' => fn (Request $request) => $request->method() === 'PUT'
            ? Http::response(['data' => ['name' => 'Kadi_Player']])
            : Http::response([], 500),
    ]);
    $user = User::factory()->create(['linked_id' => 7203, 'name' => 'Kadi_Player']);

    Livewire::actingAs($user)->test(Show::class)
        ->call('updateProfile')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && ! array_key_exists('id_no', $request->data()));
});

// ─── Home and how-to ────────────────────────────────────────────────────────

test('the home page makes no KadiApi call for a signed-in player with a cold cache', function () {
    Http::fake([LAR_GAME_API => Http::response(['single' => ['total' => 3]]), LAR_API.'games' => Http::response(['data' => []])]);
    $user = User::factory()->create(['linked_id' => 7301]);

    $this->actingAs($user)->get(route('home'))->assertOk();

    Http::assertNotSent(kadiApiCalls());
    expect(Cache::get("kadi.customer.{$user->id}"))->toBeNull();
});

test('a failing live-players call shows the last good count, not 0', function () {
    Cache::forever('kadi.tables.last', ['single' => 40, 'tournaments' => 2, 'jackpots' => 0, 'total' => 42]);
    Http::fake([LAR_GAME_API => fn () => throw new ConnectionException('timed out')]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('42 players at the Kadi tables now');
});

test('a successful live-players call is remembered as the last good count', function () {
    Http::fake([LAR_GAME_API => Http::response(['jackpots' => ['total' => 1], 'single' => ['total' => 2], 'tournaments' => ['total' => 3]])]);

    $this->get(route('home'))->assertOk()->assertSee('6 players at the Kadi tables now');

    expect(Cache::get('kadi.tables.last'))->toBe(['single' => 2, 'tournaments' => 3, 'jackpots' => 1, 'total' => 6]);
});

test('the how-to page posts the play form from account_no without calling KadiApi', function () {
    config(['platform.token_launch' => false]);
    Http::fake();
    $user = User::factory()->create(['linked_id' => 7302, 'account_no' => 'KK-HOWTO-1']);

    $this->actingAs($user)->get(route('rules'))
        ->assertOk()
        ->assertSee('<input type="hidden" name="ggid" value="KK-HOWTO-1">', false)
        ->assertDontSee('?ggid=', false);

    Http::assertNothingSent();
});

// ─── Preloader ──────────────────────────────────────────────────────────────
