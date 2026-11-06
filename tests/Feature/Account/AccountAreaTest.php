<?php

use App\Account\ResponsiblePlay as ResponsiblePlayState;
use App\Jobs\RevokeGameSessions;
use App\Livewire\Account\Activity;
use App\Livewire\Account\CloseAccount;
use App\Livewire\Account\ResponsiblePlay;
use App\Livewire\Profile\Show;
use App\Livewire\Wallet\DepositSheet;
use App\Livewire\WalletBalance;
use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\WagerOp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Phase 6: one account area (account, wallet, activity, Invite & Earn, responsible play), the deposit
 * sheet, breaks that stop launches, and the balance refresh after a game.
 */

const ACCOUNT_API = 'https://kadi-api.test/api/v1/';

function accountPlayer(array $attributes = []): User
{
    return User::factory()->create($attributes + ['linked_id' => 7070, 'phone' => '254712345678']);
}

beforeEach(function () {
    config(['services.kadi_api.url' => ACCOUNT_API, 'services.kadi_api.key' => 'site-key']);

    // Each test may swap one answer: Http::fake stubs stack, so one dispatcher reads these.
    $this->rg = ['status' => 1, 'can_play' => true, 'exclusion' => null, 'deposit_limits' => [], 'deposit_remaining' => null];
    $this->answers = [];
    $this->balance = 100.0;

    Http::preventStrayRequests();
    Http::fake(function ($request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        foreach ($this->answers as $pattern => $answer) {
            if (str_contains($path, $pattern)) {
                return is_callable($answer) ? $answer($request) : $answer;
            }
        }

        return match (true) {
            str_ends_with($path, '/responsible-gambling') => Http::response(['success' => true, 'data' => $this->rg]),
            str_contains($path, '/deposits/') => Http::response(['status' => 'success']),
            str_contains($path, '/wagers') => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 1, 'last_page' => 1, 'total' => 0]]]),
            str_contains($path, '/customers/') => Http::response(['data' => ['balance' => $this->balance]]),
            default => Http::response([], 200),
        };
    });
});

// ─── Old addresses ───────────────────────────────────────────────────────────

test('the old pages lead to the account area', function (string $from, string $to) {
    $this->actingAs(accountPlayer());

    $this->get($from)->assertRedirect($to);
})->with([
    'profile' => ['/profile', '/account'],
    'game history' => ['/games/history', '/activity'],
    'settings' => ['/settings', '/account#profile'],
    'settings profile' => ['/settings/profile', '/account#profile'],
    'settings security' => ['/settings/security', '/account#security'],
    'settings appearance' => ['/settings/appearance', '/account#profile'],
]);

// ─── Account ─────────────────────────────────────────────────────────────────

test('the account page has the four tabs and the shared header and sub-tabs', function () {
    config(['kadi.referrals.enabled' => true]);
    $html = $this->actingAs(accountPlayer(['name' => 'Jane Wanjiru']))->get('/account')->assertOk()->getContent();

    foreach (['profile', 'security', 'notifications', 'connected'] as $tab) {
        expect($html)->toContain('data-test="account-tab-'.$tab.'"');
    }

    expect($html)->toContain('<h1>Jane Wanjiru</h1>')
        ->and($html)->toContain('aria-label="Account"')
        ->and($html)->toContain('href="'.route('activity').'"')
        ->and($html)->toContain('href="'.route('responsible-play').'"')
        ->and($html)->toContain('href="'.route('referrals').'"')
        ->and($html)->not->toContain('👤');

    // The page itself; the shell's phone and install dialogs move over with sign-in (Phase 6b).
    $main = Str::between($html, '<main id="main"', '</main>');
    expect($main)->not->toContain("font-family: 'Cinzel'");
});

test('wallet, Invite & Earn, activity and responsible play share the account header', function (string $path) {
    config(['kadi.referrals.enabled' => true]);

    $this->actingAs(accountPlayer())->get($path)->assertOk()
        ->assertSee('class="nt-panel nt-acct-head"', false)
        ->assertSee('class="nt-tabs nt-subnav"', false)
        ->assertDontSee('💰');
})->with(['/wallet', '/referrals', '/activity', '/responsible-play']);

test('the password can be changed from the account page', function () {
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('currentPassword', 'password')->set('newPassword', 'N3w-password!')->set('newPasswordConfirmation', 'N3w-password!')
        ->call('updatePassword')->assertHasNoErrors();

    expect(Hash::check('N3w-password!', $user->fresh()->password))->toBeTrue();
});

test('a wrong current password is refused', function () {
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('currentPassword', 'wrong')->set('newPassword', 'N3w-password!')->set('newPasswordConfirmation', 'N3w-password!')
        ->call('updatePassword')->assertHasErrors('currentPassword');
});

// ─── Activity ────────────────────────────────────────────────────────────────

test('activity shows Kadi history first and lists slot wagers from KadiApi', function () {
    $this->answers['/wagers'] = Http::response(['success' => true, 'data' => [
        'items' => [
            ['reference' => 'w-1', 'game' => 'slots', 'product' => 'berry_blast', 'funding' => 'cash', 'stake' => 20, 'status' => 'won', 'payout' => 64, 'bonus' => 0, 'tax' => 4, 'placed_at' => '2026-11-03T10:00:00+03:00'],
            ['reference' => 'w-2', 'game' => 'slots', 'product' => 'unknown', 'funding' => 'cash', 'stake' => 10, 'status' => 'lost', 'payout' => 0, 'bonus' => 0, 'tax' => 0, 'placed_at' => '2026-11-03T09:00:00+03:00'],
        ],
        'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 2, 'last_page' => 1],
    ]]);
    $user = accountPlayer();

    Livewire::actingAs($user)->test(Activity::class)->assertSet('tab', 'kadi')->assertSeeLivewire('games.history');

    Livewire::actingAs($user)->test(Activity::class)
        ->call('show', 'slots')->call('load')
        ->assertSee('+KES 64.00')->assertSee('after KES 4.00 tax')
        ->assertSee('unknown')->assertSee('No win');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/wagers') && $request['game'] === 'slots' && $request['per_page'] === 20);
});

test('a wagers outage says so instead of showing an empty list', function () {
    $this->answers['/wagers'] = Http::response(['success' => false], 503);

    Livewire::actingAs(accountPlayer())->test(Activity::class)
        ->call('show', 'scratch')->call('load')
        ->assertSee("couldn't load your scratch cards", false)->assertDontSee('Nothing here yet');
});

test('the sports tab appears only with the sports pages', function (bool $sports) {
    config(['platform.sports' => $sports]);

    expect(array_key_exists('sports', Livewire::actingAs(accountPlayer())->test(Activity::class)->instance()->tabs()))->toBe($sports);
})->with([true, false]);

// ─── Responsible play ────────────────────────────────────────────────────────

test('the page shows the limits KadiApi holds and a running break', function () {
    $this->rg = ['status' => 1, 'can_play' => false, 'exclusion' => ['type' => 'cool_off', 'ends_at' => now()->addDays(7)->toIso8601String()],
        'deposit_limits' => [['period' => 'week', 'amount' => 5000.0, 'pending_amount' => null, 'pending_from' => null, 'remaining' => 1200.0]], 'deposit_remaining' => 1200.0];

    Livewire::actingAs(accountPlayer())->test(ResponsiblePlay::class)->call('load')
        ->assertSee("You're on a break until", false)
        ->assertSee('KES 5,000')->assertSee('KES 1,200 left now')
        ->assertSee('No limit');
});

test('a deposit limit is saved through KadiApi with an idempotency key', function () {
    $this->answers['/deposit-limits/'] = Http::response(['success' => true, 'data' => ['period' => 'day', 'amount' => 500.0, 'pending_amount' => null, 'pending_from' => null]]);
    $user = accountPlayer();

    Livewire::actingAs($user)->test(ResponsiblePlay::class)
        ->set('period', 'day')->set('limitAmount', '500')->call('saveLimit')
        ->assertSet('limitError', null)->assertSee('Your daily limit applies now');

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/deposit-limits/day')
        && $request['amount'] === 500 && $request->hasHeader('Idempotency-Key'));
});

test('a raise that waits a day says when it applies, and an empty amount removes the limit', function () {
    $this->answers['/deposit-limits/'] = Http::response(['success' => true, 'data' => ['period' => 'month', 'amount' => 1000.0, 'pending_amount' => null, 'pending_from' => now()->addDay()->toIso8601String()]]);

    Livewire::actingAs(accountPlayer())->test(ResponsiblePlay::class)
        ->set('period', 'month')->set('limitAmount', '')->call('saveLimit')
        ->assertSee('Your monthly limit changes on');

    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['amount'] === null);
});

test('a limit outside the bounds never reaches KadiApi', function (string $amount) {
    Livewire::actingAs(accountPlayer())->test(ResponsiblePlay::class)
        ->set('limitAmount', $amount)->call('saveLimit')->assertSee('Enter whole shillings');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'deposit-limits'));
})->with(['5', '12.50', 'abc', '2000000']);

test('a break needs confirming, then starts through KadiApi and closes open games', function () {
    Queue::fake();
    $this->answers['/exclusion'] = Http::response(['success' => true, 'data' => ['type' => 'cool_off', 'duration' => '7d', 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addDays(7)->toIso8601String()]], 201);
    $user = accountPlayer();

    $page = Livewire::actingAs($user)->test(ResponsiblePlay::class)
        ->set('breakChoice', 'cool_off:7d')->call('reviewBreak')
        ->assertSet('confirmingBreak', true)->assertSee('Start a 7 days break now?');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/exclusion'));

    $page->call('startBreak')->assertSee('Your break has started');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/exclusion')
        && $request['type'] === 'cool_off' && $request['duration'] === '7d' && $request->hasHeader('Idempotency-Key'));
    Queue::assertPushed(RevokeGameSessions::class, fn ($job) => $job->customerId === 7070 && $job->game === null);
});

test('a shorter break than the running one is refused with KadiApi\'s reason', function () {
    $this->answers['/exclusion'] = Http::response(['success' => false, 'code' => 'exclusion_shorter', 'message' => 'The current break already lasts longer.'], 422);

    Livewire::actingAs(accountPlayer())->test(ResponsiblePlay::class)
        ->set('breakChoice', 'cool_off:24h')->call('reviewBreak')->call('startBreak')
        ->assertSee('A break can be made longer, never shorter');
});

test('only offered break lengths can be chosen', function () {
    Livewire::actingAs(accountPlayer())->test(ResponsiblePlay::class)
        ->set('breakChoice', 'cool_off:1h')->call('reviewBreak')
        ->assertSet('confirmingBreak', false)->assertSee('Choose how long your break lasts');
});

test('a failed lookup is not cached as the state: the next try after the back-off asks again', function () {
    $user = accountPlayer();
    $this->answers['/responsible-gambling'] = Http::response([], 503);
    expect(app(ResponsiblePlayState::class)->state($user))->toBeNull();

    unset($this->answers['/responsible-gambling']);
    $this->travel(11)->seconds();
    app()->forgetScopedInstances(); // a new request

    expect(app(ResponsiblePlayState::class)->state($user)['can_play'])->toBeTrue();
});

test('a long self-exclusion is remembered beyond a day, so Play Kadi stays disabled', function () {
    $user = accountPlayer();
    $this->rg['exclusion'] = ['type' => 'self_exclusion', 'ends_at' => now()->addMonths(6)->toIso8601String()];
    app(ResponsiblePlayState::class)->state($user);

    $this->travel(3)->days();
    $this->answers['/responsible-gambling'] = Http::response([], 503);

    expect(app(ResponsiblePlayState::class)->onBreak($user, live: false))->toBeTrue();
});

test('a running break is remembered through a KadiApi outage', function () {
    $user = accountPlayer();
    $this->rg['exclusion'] = ['type' => 'cool_off', 'ends_at' => now()->addDay()->toIso8601String()];
    $state = app(ResponsiblePlayState::class);
    expect($state->onBreak($user))->toBeTrue();

    $state->forget($user);
    $this->answers['/responsible-gambling'] = Http::response([], 503);

    expect(app(ResponsiblePlayState::class)->onBreak($user))->toBeTrue();
});

// ─── Deposit sheet ───────────────────────────────────────────────────────────

test('the deposit sheet sends an STK push and then watches the balance', function () {
    $user = accountPlayer();

    $sheet = Livewire::actingAs($user)->test(DepositSheet::class)
        ->dispatch('open-deposit')->assertSet('open', true)
        ->call('pick', 1000)->call('send')
        ->assertSet('stage', 'sent')->assertSee('Check your phone');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/deposits/') && $request['amount'] === '1000');

    $this->balance = 1050.0;
    $sheet->call('check')->assertSet('stage', 'received')->assertDispatched('wallet-refreshed');
});

test('the deposit sheet compares against a fresh balance, never a stale or missing one', function () {
    $user = accountPlayer();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 9999], 3600); // stale profile copy
    $this->balance = 300.0;

    $sheet = Livewire::actingAs($user)->test(DepositSheet::class)->set('amount', '100')->call('send')
        ->assertSet('baseline', 300.0);

    $sheet->call('check')->assertSet('stage', 'sent');

    $this->balance = 395.0;
    $sheet->call('check')->assertSet('stage', 'received');
});

test('the deposit sheet shows what reaches the wallet after excise duty', function () {
    Livewire::actingAs(accountPlayer())->test(DepositSheet::class)
        ->set('amount', '500')->assertSee('KES 25.00')->assertSee('KES 475.00');
});

test('without a confirmed phone the sheet hands over to the phone sheet and resumes after', function () {
    $user = accountPlayer(['phone' => '254712345678', 'phone_verified_at' => null]);

    Livewire::actingAs($user)->test(DepositSheet::class)
        ->dispatch('open-deposit')->call('send')
        ->assertDispatched('open-phone-required', purpose: 'deposit')->assertSet('open', false)
        ->dispatch('phone-saved')->assertSet('open', true);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/deposits/'));
});

test('the deposit sheet refuses deposits during a break, below the minimum and above the limit', function (array $rg, string $amount, string $message) {
    $this->rg = $rg + $this->rg;

    Livewire::actingAs(accountPlayer())->test(DepositSheet::class)
        ->set('amount', $amount)->call('send')->assertSee($message);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/deposits/'));
})->with([
    'on a break' => [['exclusion' => ['type' => 'cool_off', 'ends_at' => '2099-01-01T00:00:00+03:00']], '500', 'deposits are paused'],
    'below the minimum' => [[], '5', 'The smallest deposit is KES 10'],
    'above the limit' => [['deposit_remaining' => 300.0], '500', 'Your deposit limit allows KES 300 more'],
]);

test('the top bar Deposit opens the sheet on every page', function () {
    $this->actingAs(accountPlayer())->get(route('faq'))->assertOk()
        ->assertSee("\$dispatch('open-deposit')", false)
        ->assertSee('data-sheet="deposit"', false);
});

test('an M-Pesa request that fails says so and leaves the sheet ready to try again', function () {
    $this->answers['/deposits/'] = Http::response(['message' => 'down'], 503);

    Livewire::actingAs(accountPlayer())->test(DepositSheet::class)
        ->set('amount', '500')->call('send')
        ->assertSet('stage', null)
        ->assertSee("We couldn't send the M-Pesa request.");
});

test('linking Google lands on the account page with its message', function () {
    $this->actingAs(accountPlayer())->withSession(['status' => 'google-linked'])->get('/account')->assertOk()->assertSee('Your Google account is linked.');
});

test('the current sub-tab stays marked after a Livewire update', function () {
    $html = Livewire::actingAs(accountPlayer())->test(Activity::class)->call('show', 'slots')->html();

    expect($html)->toMatch('#class="nt-tab"\s+aria-current="page"\s*>Activity</a>#');
});

// ─── Back from a game ────────────────────────────────────────────────────────

test('coming back from a game asks KadiApi for the balance, at most every 10 seconds', function () {
    $user = accountPlayer();
    Cache::put("wallet_balance_{$user->id}", 100.0, 60);
    $this->balance = 250.0;

    $pill = Livewire::actingAs($user)->test(WalletBalance::class, ['variant' => 'pill'])
        ->dispatch('game-returned')->assertSet('balance', 250.0);

    $this->balance = 300.0;
    $pill->dispatch('game-returned')->assertSet('balance', 250.0);
});

// ─── Close account ───────────────────────────────────────────────────────────

test('closing an account asks KadiApi first, then deletes the player here and logs them out', function () {
    Queue::fake();
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', 'password')->set('confirmWord', 'delete')->call('close')
        ->assertRedirect('/');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/customers/'));
    expect(User::find($user->id))->toBeNull()->and(auth()->check())->toBeFalse();
    Queue::assertPushed(RevokeGameSessions::class, fn ($job) => $job->customerId === 7070);
});

test('KadiApi refusing because of money or activity keeps the account and says what to do', function () {
    $this->answers['/customers/'] = fn ($request) => $request->method() === 'DELETE'
        ? Http::response(['message' => 'Customer cannot be deleted', 'open' => ['wallet_balance', 'open_game']], 409)
        : Http::response(['data' => ['balance' => 50]]);
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', 'password')->set('confirmWord', 'DELETE')->call('close')
        ->assertSee('Withdraw or use up your wallet balance first.')
        ->assertSee('Finish the Kadi game you are in first.');

    expect(User::find($user->id))->not->toBeNull();
});

test('a sports bet still to settle, or a settlement not yet sent, keeps the account open without asking KadiApi', function (BetStatus $status, ?string $opStatus) {
    $user = accountPlayer(['password' => Hash::make('password')]);
    $bet = Bet::factory()->for($user)->create(['status' => $status]);

    if ($opStatus !== null) {
        WagerOp::query()->create([
            'product' => 'sportsbook', 'wager_reference' => $bet->wager_reference, 'subject_type' => 'bet', 'subject_id' => $bet->id,
            'operation' => 'settle', 'version' => 1, 'payload' => [], 'fingerprint' => str_repeat('a', 64), 'status' => $opStatus,
        ]);
    }

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', 'password')->set('confirmWord', 'DELETE')->call('close')
        ->assertSee('Wait for your sports bets and jackpot entries to settle first.');

    expect(User::find($user->id))->not->toBeNull();
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
})->with([
    'open bet' => [BetStatus::Open, null],
    'being checked' => [BetStatus::Pending, null],
    'settled, payout not sent' => [BetStatus::Won, 'pending'],
    'settled, payout refused' => [BetStatus::Won, 'failed'],
]);

test('a closed account keeps its settled sports bets for the books, without the player', function () {
    Queue::fake();
    $user = accountPlayer(['password' => Hash::make('password')]);
    $bet = Bet::factory()->for($user)->create(['status' => BetStatus::Lost]);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', 'password')->set('confirmWord', 'DELETE')->call('close')
        ->assertRedirect('/');

    expect(User::find($user->id))->toBeNull()
        ->and($bet->fresh())->not->toBeNull()->user_id->toBeNull();
});

test('a wrong password or a missing confirmation never reaches KadiApi', function (string $password, string $word, string $field) {
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', $password)->set('confirmWord', $word)->call('close')
        ->assertHasErrors($field);

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    expect(User::find($user->id))->not->toBeNull();
})->with([
    'wrong password' => ['nope', 'DELETE', 'password'],
    'no confirmation' => ['password', 'yes', 'confirmWord'],
]);

test('a KadiApi outage changes nothing', function () {
    $this->answers['/customers/'] = fn ($request) => $request->method() === 'DELETE' ? Http::response([], 503) : Http::response(['data' => ['balance' => 0]]);
    $user = accountPlayer(['password' => Hash::make('password')]);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->set('password', 'password')->set('confirmWord', 'DELETE')->call('close')
        ->assertSee('Nothing was changed');

    expect(User::find($user->id))->not->toBeNull();
});

test('a Google player confirms by typing the word', function () {
    Queue::fake();
    $user = accountPlayer(['google_id' => 'g-123']);

    Livewire::actingAs($user)->test(CloseAccount::class)
        ->call('start')->assertDontSee('Your password')->set('confirmWord', 'DELETE')->call('close')
        ->assertRedirect('/');

    expect(User::find($user->id))->toBeNull();
});

test('the Security tab offers closing the account', function () {
    $this->actingAs(accountPlayer())->get('/account')->assertOk()->assertSee('data-test="close-account-start"', false);
});
