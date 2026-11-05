<?php

use App\Livewire\Referrals\Index;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

const REF_PAGE_API = 'https://api.kadi-kings.co.ke/api/v1/';

function fakeReferralPageApi(array $overrides = []): void
{
    Http::fake([
        REF_PAGE_API.'customers/*/referral-wallet/withdrawals*' => $overrides['withdrawals'] ?? Http::response([
            'data' => [['id' => 5, 'amount' => 100.0, 'status' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'created_at' => '2026-09-20T10:00:00+03:00']],
            'links' => [], 'meta' => ['current_page' => 1, 'last_page' => 1],
        ]),
        REF_PAGE_API.'customers/*/referral-wallet/withdraw' => $overrides['withdraw'] ?? Http::response([
            'success' => true, 'data' => ['id' => 6, 'status' => 'processing'],
        ], 201),
        REF_PAGE_API.'customers/*/referral-wallet*' => $overrides['wallet'] ?? Http::response([
            'success' => true,
            'data' => [
                'balance' => 120.0, 'total_earned' => 150.0, 'withdrawable' => true, 'minimum_withdrawal' => 50,
                'bonuses' => [['id' => 31, 'referred_name' => 'New Player', 'milestone' => 'first_deposit', 'amount' => 10.0, 'created_at' => '2026-09-21T10:00:00+03:00']],
            ],
            'pagination' => ['page' => 1, 'per_page' => 10, 'total' => 1, 'last_page' => 1],
        ]),
        REF_PAGE_API.'customers/*/referrals/stats' => $overrides['stats'] ?? Http::response(['success' => true, 'data' => [
            'code' => 'KADI2026',
            'referrals' => ['total' => 12, 'verified' => 9, 'deposited' => 6, 'pending_verification' => 3, 'today' => 1, 'this_week' => 4, 'this_month' => 12],
            'earned' => ['total' => 150.0, 'signup' => 90.0, 'first_deposit' => 60.0, 'this_month' => 150.0],
            'wallet_balance' => 120.0,
        ]]),
        REF_PAGE_API.'customers/*/referrals*' => $overrides['referrals'] ?? Http::response([
            'data' => [
                ['id' => 7, 'referred_name' => 'New Player', 'referred_phone' => '2547****5678', 'status' => 'deposited', 'earned' => 20.0, 'created_at' => '2026-09-19T10:00:00+03:00'],
                ['id' => 8, 'referred_name' => 'Other Friend', 'referred_phone' => '2547****1111', 'status' => 'pending_verification', 'earned' => 0.0, 'created_at' => '2026-09-22T10:00:00+03:00'],
            ],
            'links' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
        ]),
        REF_PAGE_API.'customers/*/referral-code' => Http::response(['data' => ['code' => 'KADI2026', 'link' => 'https://kadi.test/register?ref=KADI2026', 'qr_code' => null]]),
    ]);
}

function referrer(array $attributes = []): User
{
    return User::factory()->create(['linked_id' => 42, 'phone' => '254712345678', ...$attributes]);
}

function withdrawCalls(): Closure
{
    return fn (Request $request) => str_ends_with($request->url(), '/referral-wallet/withdraw');
}

test('the page renders the code, stats, referrals, wallet, bonuses and withdrawals', function () {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->assertSee('KADI2026')
        ->assertSee('https://kadi.test/register?ref=KADI2026')
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertSeeHtml('data-test="referral-balance">KES 120</p>')
        ->assertSee('KES 150.00')
        ->assertSeeInOrder(['Invited', '12', 'Verified', '9', 'Deposited', '6', 'Pending', '3'])
        ->assertSee('New Player')
        ->assertSee('2547****5678')
        ->assertSee('Pending verification')
        ->assertSee('First deposit')
        ->assertSee('RKA1B2C3D4')
        ->assertSee('https://wa.me/?text=', false);
});

test('the route is reachable for a verified player', function () {
    fakeReferralPageApi();

    $this->actingAs(referrer())->get(route('referrals'))->assertOk()->assertSee('Invite &amp; Earn', false);
});

test('the page is hidden when referrals are switched off', function () {
    config(['kadi.referrals.enabled' => false]);

    $this->actingAs(referrer())->get(route('referrals'))->assertNotFound();
});

test('filtering by status asks KadiApi for that status', function () {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->call('setStatus', 'verified');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/referrals?')
        && ($request->data()['status'] ?? null) === 'verified'
        && (int) ($request->data()['page'] ?? 0) === 1);
});

test('one failing block does not blank the rest of the page', function () {
    fakeReferralPageApi(['stats' => Http::response([], 500)]);

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->assertSet('statsFailed', true)
        ->assertSee("We couldn't load your stats right now.", false)
        ->assertSee('New Player')
        ->assertSeeHtml('data-test="referral-balance">KES 120</p>');
});

test('withdraw validation never reaches KadiApi', function (string $amount, string $error) {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', $amount)
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('withdrawError', $error);

    Http::assertNotSent(withdrawCalls());
})->with([
    'below the minimum' => ['49', 'The minimum referral withdrawal is KES 50.'],
    'above the balance' => ['121', 'Insufficient referral balance for this withdrawal.'],
    'not whole shillings' => ['60.5', 'Enter a whole number of shillings.'],
    'empty' => ['', 'Enter a whole number of shillings.'],
]);

test('exactly the minimum and exactly the balance reach confirmation', function (string $amount) {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', $amount)
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', true)
        ->assertSet('withdrawError', null);
})->with(['50', '120']);

test('an unconfirmed phone opens the phone modal instead of withdrawing', function () {
    fakeReferralPageApi();

    Livewire::actingAs(referrer()->fresh()->forceFill(['phone_verified_at' => null]))->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '60')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertDispatched('open-phone-required', purpose: 'referral');

    Http::assertNotSent(withdrawCalls());
});

test('a wallet that is not withdrawable refuses', function () {
    fakeReferralPageApi(['wallet' => Http::response(['data' => ['balance' => 120, 'withdrawable' => false, 'minimum_withdrawal' => 50, 'bonuses' => []]])]);

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '60')
        ->call('requestWithdraw')
        ->assertSet('withdrawError', 'Referral withdrawals are not available right now.');
});

test('each KadiApi answer shows its message', function ($response, ?string $success, ?string $notice, ?string $error) {
    fakeReferralPageApi(['withdraw' => $response]);

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '100')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('successMessage', $success)
        ->assertSet('noticeMessage', $notice)
        ->assertSet('withdrawError', $error);

    Http::assertSent(fn (Request $request) => withdrawCalls()($request)
        && $request['amount'] === 100
        && $request->hasHeader('Idempotency-Key'));
})->with([
    '201 processing' => [fn () => Http::response(['data' => ['status' => 'processing']], 201), "Payout sent, you'll receive an M-Pesa message shortly.", null, null],
    '202 pending' => [fn () => Http::response(['data' => ['status' => 'pending']], 202), null, 'Processing, check back later.', null],
    '502 restored' => [fn () => Http::response(['data' => ['status' => 'failed']], 502), null, null, 'Payout failed, your balance was restored.'],
    '400 balance' => [fn () => Http::response(['success' => false, 'message' => 'Insufficient referral wallet balance'], 400), null, null, 'Insufficient referral balance for this withdrawal.'],
    '400 phone' => [fn () => Http::response(['success' => false, 'message' => 'No valid M-Pesa phone number'], 400), null, null, 'We could not find a valid M-Pesa number on your account. Please contact support.'],
    '403 switched off' => [fn () => Http::response(['success' => false], 403), null, null, 'Referral withdrawals are paused right now. Please try again later.'],
    '422 minimum' => [fn () => Http::response(['success' => false, 'message' => 'The minimum referral withdrawal is KES 50'], 422), null, null, 'The minimum referral withdrawal is KES 50.'],
    '503 unavailable' => [fn () => Http::response(['success' => false], 503), null, null, 'Referral withdrawals are temporarily unavailable. Please try again later.'],
]);

test('a timeout is treated as pending, never as failed', function () {
    fakeReferralPageApi(['withdraw' => fn () => throw new ConnectionException('timed out')]);

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '100')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->assertSet('noticeMessage', 'Processing, check back later.')
        ->assertSet('withdrawError', null)
        ->assertSet('pendingAmount', 100);
});

test('a double confirm sends one payout', function () {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '100')
        ->call('requestWithdraw')
        ->call('confirmWithdraw')
        ->call('confirmWithdraw');

    expect(Http::recorded(withdrawCalls()))->toHaveCount(1);
});

test('each attempt gets its own key, but a retry after an unknown outcome reuses it', function () {
    fakeReferralPageApi(['withdraw' => Http::sequence()
        ->push(['data' => ['status' => 'processing']], 201)
        ->push(['data' => ['status' => 'pending']], 202)
        ->push(['data' => ['status' => 'pending']], 202)
        ->push(['data' => ['status' => 'processing']], 201)]);

    $page = Livewire::actingAs(referrer())->test(Index::class)->call('load');

    foreach (['50', '60', '60', '70'] as $amount) {
        $page->set('withdrawAmount', $amount)->call('requestWithdraw')->call('confirmWithdraw');
    }

    $keys = Http::recorded(withdrawCalls())->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0])->values();

    expect($keys)->toHaveCount(4)
        ->and($keys[0])->not->toBe($keys[1])
        ->and($keys[1])->toBe($keys[2])   // same amount after a 202: replayed, not paid twice
        ->and($keys[3])->not->toBe($keys[2]);
});

test('the balance and history are re-read after a withdrawal', function () {
    fakeReferralPageApi();

    Livewire::actingAs(referrer())->test(Index::class)
        ->call('load')
        ->set('withdrawAmount', '100')
        ->call('requestWithdraw')
        ->call('confirmWithdraw');

    // Once on load, once after the payout (the 60s cache is cleared).
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), '/referral-wallet?')))->toHaveCount(2);
});

test('the browser cannot rewrite the wallet, list or idempotency key', function (string $property, mixed $value) {
    fakeReferralPageApi();

    expect(fn () => Livewire::actingAs(referrer())->test(Index::class)->call('load')->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'wallet' => ['wallet', ['balance' => 999999, 'withdrawable' => true, 'minimum_withdrawal' => 1]],
    'referrals' => ['referrals', []],
    'pending key' => ['pendingKey', 'attacker-chosen'],
]);
