<?php

use App\Livewire\Referrals\Index as ReferralsIndex;
use App\Livewire\Wallet\Index as WalletIndex;
use App\Livewire\WalletBalance;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const FLOOR_API = 'https://api.kadi-kings.co.ke/api/v1/';

test('the header balance is floored, never rounded up', function () {
    $user = User::factory()->create();
    Cache::put("wallet_balance_{$user->id}", 1499.6, now()->addMinutes(5));

    Livewire::actingAs($user)->test(WalletBalance::class)
        ->assertSee('1,499')
        ->assertDontSee('1,500');
});

test('the wallet page balance is floored, never rounded up', function () {
    Http::fake([
        FLOOR_API.'customers/*/promotions' => Http::response(['success' => true, 'data' => ['locked_amount' => 0, 'items' => []]]),
        FLOOR_API.'customers/transactions/*' => Http::response(['transactions' => []]),
        FLOOR_API.'customers/*' => Http::response(['data' => ['balance' => 1499.6, 'account_no' => 'KK-TEST']]),
    ]);
    $user = User::factory()->create(['linked_id' => 4242, 'phone' => '254712345678']);
    Cache::put("kadi.customer.{$user->id}", ['balance' => 1499.6, 'account_no' => 'KK-TEST'], now()->addHour());

    Livewire::actingAs($user)->test(WalletIndex::class)
        ->assertSee('1,499')
        ->assertDontSee('1,500');
});

test('the top bar wallet pill is floored, never rounded up', function () {
    $user = User::factory()->create();
    Cache::put("wallet_balance_{$user->id}", 777.9, now()->addMinutes(5));

    $this->actingAs($user)
        ->get(route('faq'))
        ->assertOk()
        ->assertSee('KES 777', false)
        ->assertDontSee('KES 778', false);
});

test('the referral wallet balance is floored to whole shillings', function () {
    Http::fake([
        FLOOR_API.'customers/*/referral-wallet/withdrawals*' => Http::response(['data' => [], 'links' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]]),
        FLOOR_API.'customers/*/referral-wallet*' => Http::response([
            'success' => true,
            'data' => ['balance' => 120.9, 'total_earned' => 150.0, 'withdrawable' => true, 'minimum_withdrawal' => 50, 'bonuses' => []],
            'pagination' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1],
        ]),
        FLOOR_API.'customers/*/referrals/stats' => Http::response(['success' => true, 'data' => [
            'code' => 'KADI2026',
            'referrals' => ['total' => 0, 'verified' => 0, 'deposited' => 0, 'pending_verification' => 0, 'today' => 0, 'this_week' => 0, 'this_month' => 0],
            'earned' => ['total' => 150.0, 'signup' => 150.0, 'first_deposit' => 0.0, 'this_month' => 0.0],
            'wallet_balance' => 120.9,
        ]]),
        FLOOR_API.'customers/*/referrals*' => Http::response(['data' => [], 'links' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]),
        FLOOR_API.'customers/*/referral-code' => Http::response(['data' => ['code' => 'KADI2026', 'link' => 'https://kadi.test/register?ref=KADI2026', 'qr_code' => null]]),
    ]);
    $user = User::factory()->create(['linked_id' => 42, 'phone' => '254712345678']);

    Livewire::actingAs($user)->test(ReferralsIndex::class)
        ->call('load')
        ->assertSeeHtml('data-test="referral-balance">KES 120</p>')
        ->assertSee('KES 150.00');
});
