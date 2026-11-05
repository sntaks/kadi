<?php

use App\Livewire\Wallet\Index;
use App\Livewire\WalletBalance;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const POLL_API_BASE = 'https://api.kadi-kings.co.ke/api/v1/';

function pollUser(): User
{
    return User::factory()->create(['linked_id' => 6161]);
}

function fakePollApi(float $balance = 500.0): void
{
    Http::fake([
        POLL_API_BASE.'customers/*' => Http::response(['data' => ['balance' => $balance]]),
        POLL_API_BASE.'customers/transactions/*' => Http::response(['transactions' => []]),
    ]);
}

test('nav widget polls every 30 seconds while visible', function () {
    $user = pollUser();
    Cache::put("wallet_balance_{$user->id}", 10.0, now()->addSeconds(20));

    Livewire::actingAs($user)->test(WalletBalance::class)
        ->assertSeeHtml('wire:poll.30s.visible="pollBalance"');
});

test('wallet page polls every 30 seconds while visible', function () {
    $user = pollUser();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 10.0], now()->addHour());

    Livewire::actingAs($user)->test(Index::class)
        ->assertSeeHtml('wire:poll.30s.visible="pollBalance"');
});

test('the balance cache expires before the next poll tick', function () {
    expect(WalletBalance::BALANCE_TTL_SECONDS)->toBeLessThan(30);
});

test('nav poll refetches from the api when the balance cache is cold', function () {
    fakePollApi(500.0);
    $user = pollUser();

    Livewire::actingAs($user)->test(WalletBalance::class)
        ->call('pollBalance')
        ->assertSet('balance', 500.0)
        ->assertSet('hasError', false);

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(500.0);
});

test('nav poll serves the warm cache without an api call', function () {
    fakePollApi(500.0);
    $user = pollUser();
    Cache::put("wallet_balance_{$user->id}", 123.0, now()->addSeconds(20));

    Livewire::actingAs($user)->test(WalletBalance::class)
        ->call('pollBalance')
        ->assertSet('balance', 123.0);

    Http::assertNothingSent();
});

test('nav poll picks up a balance changed by another service', function () {
    fakePollApi(900.0);
    $user = pollUser();
    Cache::put("wallet_balance_{$user->id}", 100.0, now()->addSeconds(20));

    $component = Livewire::actingAs($user)->test(WalletBalance::class)
        ->assertSet('balance', 100.0);

    // The 25s cache lapses before the next tick.
    Cache::forget("wallet_balance_{$user->id}");

    $component->call('pollBalance')->assertSet('balance', 900.0);
});

test('nav poll does nothing for a user without a linked id', function () {
    fakePollApi();
    $user = User::factory()->create(['linked_id' => null]);

    Livewire::actingAs($user)->test(WalletBalance::class)
        ->call('pollBalance')
        ->assertSet('balance', null)
        ->assertSet('hasError', false);

    Http::assertNothingSent();
});

test('a failed nav poll keeps the last known balance instead of showing an error', function () {
    $user = pollUser();
    Cache::put("wallet_balance_{$user->id}", 250.0, now()->addSeconds(20));

    $component = Livewire::actingAs($user)->test(WalletBalance::class)
        ->assertSet('balance', 250.0);

    Cache::forget("wallet_balance_{$user->id}");
    Cache::forget("kadi.customer.{$user->id}");
    Http::fake([POLL_API_BASE.'customers/*' => Http::response([], 500)]);

    $component->call('pollBalance')
        ->assertSet('balance', 250.0)
        ->assertSet('hasError', false);
});

test('wallet page poll refetches when the balance cache is cold', function () {
    fakePollApi(640.0);
    $user = pollUser();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 10.0], now()->addHour());

    $page = Livewire::actingAs($user)->test(Index::class);
    // The account frame's balance seeds the cache while the page renders; the next poll finds it expired.
    Cache::forget("wallet_balance_{$user->id}");

    $page->call('pollBalance')->assertSet('balance', 640.0);

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(640.0);
});

test('wallet page poll serves the warm cache without an api call', function () {
    fakePollApi(640.0);
    $user = pollUser();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 77.0], now()->addHour());
    Cache::put("wallet_balance_{$user->id}", 77.0, now()->addSeconds(20));

    Livewire::actingAs($user)->test(Index::class)
        ->call('pollBalance')
        ->assertSet('balance', 77.0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/customers/') && ! str_contains($request->url(), 'transactions') && ! str_ends_with($request->url(), '/promotions'));
});

test('wallet page poll is skipped while a withdrawal is being sent', function () {
    fakePollApi(640.0);
    $user = pollUser();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 10.0], now()->addHour());

    $page = Livewire::actingAs($user)->test(Index::class)->set('processingWithdraw', true);
    Cache::forget("wallet_balance_{$user->id}");

    $page->call('pollBalance')->assertSet('balance', 10.0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/customers/') && ! str_contains($request->url(), 'transactions') && ! str_ends_with($request->url(), '/promotions'));
});
