<?php

use App\Livewire\Wallet\Index;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const WALLET_API_BASE = 'https://api.kadi-kings.co.ke/api/v1/';

function fakeWalletApi(array $overrides = []): void
{
    Http::fake([
        WALLET_API_BASE.'withdraw/*' => $overrides['withdraw'] ?? Http::response(['status' => 'success', 'ledger_entry_id' => 'LE-9'], 201),
        WALLET_API_BASE.'customers/transactions/*' => Http::response(['transactions' => []]),
        WALLET_API_BASE.'customers/*' => $overrides['customer'] ?? Http::response([
            'data' => ['balance' => 4700.0, 'account_no' => 'KK-TEST'],
        ]),
    ]);
}

function withdrawScenario(float $balance = 5000): User
{
    $user = User::factory()->create(['linked_id' => 4242, 'phone' => '254712345678']);

    if ($balance > 0) {
        Cache::put("kadi.customer.{$user->id}", [
            'balance' => $balance,
            'account_no' => 'KK-TEST',
        ], now()->addHour());
    }

    return $user;
}

function withdrawSent(): Closure
{
    return fn ($request) => str_contains($request->url(), '/withdraw/');
}

test('request withdraw below minimum is rejected and never reaches confirmation or api', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '49')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawError', 'Minimum withdrawal amount is KES 50.');

    Http::assertNotSent(withdrawSent());
});

test('the minimum withdrawal of exactly KES 50 reaches confirmation', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '50')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', true)
        ->assertSet('withdrawError', null);
});

test('request withdraw above balance shows insufficient error', function () {
    fakeWalletApi();
    $user = withdrawScenario(balance: 200);

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawError', 'Insufficient balance for this withdrawal.');

    Http::assertNotSent(withdrawSent());
});

test('valid amount reaches the confirmation step without calling the api', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', true)
        ->assertSet('showWithdrawModal', true)
        ->assertSet('withdrawKey', fn ($key) => is_string($key) && $key !== '');

    Http::assertNotSent(withdrawSent());
});

test('unlinked account cannot open a withdraw request', function () {
    fakeWalletApi();
    $user = User::factory()->create(['linked_id' => null]);

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawError', 'Your account is not linked to a vault. Please contact support.');

    Http::assertNotSent(withdrawSent());
});

test('withdraw button is disabled until the page has loaded, then enabled', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    $page = Livewire::actingAs($user)->test(Index::class);
    expect($page->html())->toMatch('#<button type="button" disabled[^>]*data-test="open-withdraw"#');

    $page->call('loadPage');
    expect($page->html())->toContain('data-test="open-withdraw"')->not->toMatch('#<button type="button" disabled[^>]*data-test="open-withdraw"#');
});

test('confirmed withdrawal closes modal refreshes caches dispatches event and reloads transactions', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', true)
        ->call('confirmWithdraw')
        ->assertSet('showWithdrawModal', false)
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawKey', null)
        ->assertSet('balance', 4700.0)
        ->assertDispatched('wallet-refreshed')
        ->assertSet('successMessage', fn ($value) => str_contains($value, 'Withdrawal sent to M-Pesa') && str_contains($value, 'LE-9'));

    expect(Cache::get("kadi.customer.{$user->id}")['balance'])->toEqual(4700.0);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), WALLET_API_BASE.'withdraw/'));
});

test('the same idempotency key is generated at confirm and sent to the api', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    $component = Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw');

    $key = $component->get('withdrawKey');
    $component->call('confirmWithdraw');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/withdraw/')
        && $request->header('Idempotency-Key') === [$key]);
});

test('going back from confirmation discards the key so an edited amount gets a new one', function () {
    fakeWalletApi();
    $user = withdrawScenario();

    $component = Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw');

    $first = $component->get('withdrawKey');

    $component->call('cancelWithdraw')
        ->assertSet('withdrawKey', null)
        ->set('withdrawAmount', '400')
        ->call('requestWithdraw');

    expect($component->get('withdrawKey'))->not->toBe($first);
});

test('rejected withdrawal returns to amount step with the specific error and resyncs balance', function () {
    fakeWalletApi(['withdraw' => Http::response(['status' => 'M-Pesa B2C failed'], 500)]);
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('showWithdrawModal', true)
        ->assertSet('withdrawKey', null)
        ->assertSet('withdrawError', fn ($value) => str_contains($value, 'not charged'));
});

test('rate limited withdrawal shows a wait message', function () {
    fakeWalletApi(['withdraw' => Http::response(['message' => 'Too Many Attempts.'], 429)]);
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('withdrawError', fn ($value) => str_contains($value, 'wait a minute'));
});

test('a timeout is shown as pending and never as failed', function () {
    fakeWalletApi(['withdraw' => function () {
        throw new ConnectionException('Connection timed out');
    }]);
    $user = withdrawScenario();

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '300')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('showWithdrawModal', false)
        ->assertSet('withdrawError', null)
        ->assertSet('successMessage', fn ($value) => str_contains($value, 'Check your transaction history'))
        ->assertDispatched('wallet-refreshed');
});
