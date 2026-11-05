<?php

use App\Livewire\Wallet\Index;
use App\Models\User;
use App\Services\KadiApiService;
use App\Services\WithdrawResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const LOCKED_API = 'https://api.kadi-kings.co.ke/api/v1/';

function fakeLockedApi(float $locked, array $withdraw = ['status' => 'success', 'ledger_entry_id' => 'LE-1'], int $withdrawStatus = 201): void
{
    Http::fake([
        LOCKED_API.'customers/*/promotions' => Http::response(['success' => true, 'data' => [
            'locked_amount' => $locked,
            'items' => $locked > 0 ? [[
                'id' => 7, 'promotion' => 'signup_bonus', 'promo_code' => 'KADI20', 'net_amount' => 20,
                'wagered' => 20 - $locked, 'locked_amount' => $locked, 'unlocked' => false, 'status' => 'granted',
            ]] : [],
        ]]),
        LOCKED_API.'withdraw/*' => Http::response($withdraw, $withdrawStatus),
        LOCKED_API.'customers/transactions/*' => Http::response(['transactions' => []]),
        LOCKED_API.'customers/*' => Http::response(['data' => ['balance' => 70, 'account_no' => 'KK-TEST']]),
    ]);
}

function lockedWalletUser(float $balance = 70): User
{
    $user = User::factory()->create(['linked_id' => 4242, 'phone' => '254712345678']);
    Cache::put("kadi.customer.{$user->id}", ['balance' => $balance, 'account_no' => 'KK-TEST'], now()->addHour());

    return $user;
}

$lockedBody = [
    'status' => 'KES 20 of the signup bonus must be staked before it can be withdrawn or moved',
    'ledger_entry_id' => null,
    'code' => 'signup_bonus_locked',
    'locked_amount' => 20,
    'available' => 5,
];

test('the wallet shows what is still locked and caps the withdraw amount', function () {
    fakeLockedApi(15);
    $user = lockedWalletUser(70);

    Livewire::actingAs($user)->test(Index::class)
        ->call('loadPage')
        ->assertSet('lockedBonus', 15.0)
        ->assertSee('Signup bonus: KES 15 left to play before you can withdraw it.')
        ->assertSeeHtml('data-test="withdrawable">KES 55</b>');
});

test('nothing locked: no note, full balance withdrawable', function () {
    fakeLockedApi(0);
    $user = lockedWalletUser(70);

    Livewire::actingAs($user)->test(Index::class)
        ->call('loadPage')
        ->assertSet('lockedBonus', 0.0)
        ->assertDontSee('data-test="locked-bonus-note"', false)
        ->assertSeeHtml('data-test="withdrawable">KES 70</b>');
});

test('a promotions error shows nothing locked and never breaks the wallet', function () {
    Http::fake([
        LOCKED_API.'customers/*/promotions' => Http::response([], 500),
        LOCKED_API.'customers/transactions/*' => Http::response(['transactions' => []]),
    ]);
    $user = lockedWalletUser(70);

    Livewire::actingAs($user)->test(Index::class)
        ->call('loadPage')
        ->assertOk()
        ->assertSet('lockedBonus', 0.0);
});

test('a withdrawal that would use locked bonus is stopped before KadiApi', function () {
    fakeLockedApi(20);
    $user = lockedWalletUser(80);

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '70')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawError', 'KES 20 of your signup bonus has to be played first. You can withdraw up to KES 60.');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/withdraw/'));

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '60')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', true);
});

test('KadiApi signup_bonus_locked on withdraw is shown with the amounts, not as insufficient balance', function () use ($lockedBody) {
    Http::fake([LOCKED_API.'withdraw/*' => Http::response($lockedBody, 400)]);

    $result = app(KadiApiService::class)->withdraw(User::factory()->create(['linked_id' => 777]), 50, 'key-1');

    expect($result->outcome)->toBe(WithdrawResult::REJECTED)
        ->and($result->message)->toBe('KES 20 of your signup bonus has to be played first. You can withdraw up to KES 5.');
});

test('the wallet shows the signup_bonus_locked refusal when KadiApi decides differently', function () use ($lockedBody) {
    // Our cached view says nothing is locked; KadiApi knows better.
    fakeLockedApi(0, $lockedBody, 400);
    $user = lockedWalletUser(70);

    Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '60')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('withdrawError', 'KES 20 of your signup bonus has to be played first. You can withdraw up to KES 5.');
});

test('other 400s keep their old messages', function () {
    Http::fake([LOCKED_API.'withdraw/*' => Http::response(['status' => 'Insufficient wallet balance for withdrawal', 'ledger_entry_id' => null], 400)]);

    $result = app(KadiApiService::class)->withdraw(User::factory()->create(['linked_id' => 777]), 50);

    expect($result->message)->toBe('Insufficient balance for this withdrawal.');
});
