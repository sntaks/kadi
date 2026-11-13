<?php

use App\Account\ResponsiblePlay;
use App\Livewire\Account\CloseAccount;
use App\Livewire\Predict\Slip;
use App\Models\User;
use App\Predict\MarketFeed;
use App\Predict\PendingAttempt;
use App\Services\WalletBalanceFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

/**
 * The prediction bet slip (App\Livewire\Predict\Slip) and placing (App\Predict\PlaceBet): guards, quotes and their
 * expiry, the pool flow, unknown outcomes and "Try again" with the same reference, price changes, refusals,
 * estimates and their limits. The Kadi API is faked; every request is recorded.
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
    $this->player = User::factory()->create(['linked_id' => 501]);
});

/** A pool market (the live fixture) or, with $reference, a fixed-odds one. */
function slipMarket(int $id = 7, bool $reference = false, array $changes = []): array
{
    $market = array_replace_recursive(predictFixture('market-polymarket-pool')['body']['data'], ['id' => $id, 'betting_closes_at' => '2026-12-30T23:45:00+03:00'], $changes);

    if ($reference) {
        $market = array_replace_recursive($market, ['engine' => 'reference', 'odds' => ['is_reference' => true, 'attribution' => 'Reference prices from Polymarket', 'yes_multiplier' => 1.6, 'no_multiplier' => 2.4]]);
    }

    return $market;
}

function slipQuote(int $marketId = 7, string $side = 'yes', float $stake = 100, float $multiplier = 1.58, int $seconds = 10, string $id = 'q-1'): array
{
    return [
        'quote_id' => $id, 'market_id' => $marketId, 'side' => $side, 'stake' => $stake, 'multiplier' => $multiplier,
        'potential_payout' => round($stake * $multiplier, 2), 'price' => 0.63,
        'expires_at' => Date::now()->addSeconds($seconds)->toIso8601String(),
    ];
}

function slipBetAnswer(int $marketId = 7, bool $replayed = false, bool $estimate = false): array
{
    return ['success' => true, 'data' => [
        'bet' => ['id' => 41, 'market_id' => $marketId, 'side' => 'yes', 'stake' => 100, 'status' => 'open', 'multiplier' => 1.58, 'potential_payout' => 158, 'is_estimate' => $estimate, 'placed_at' => '2026-11-13T11:37:20+00:00'],
        'balance' => $replayed ? null : ['balance' => 900, 'wallet_version' => 53],
        'replayed' => $replayed,
    ]];
}

function refusalBody(string $code, string $message = 'No.', array $extra = []): array
{
    return ['success' => false, 'message' => $message, 'code' => $code, 'retryable' => false] + $extra;
}

/**
 * Fake the API: the market, its estimate, then quote and bet answers in order.
 *
 * @param  list<mixed>  $quotes
 * @param  list<mixed>  $bets  arrays are bodies (status 200 or [body, status]); 'timeout' throws
 */
function fakeSlipApi(array $market, array $quotes = [], array $bets = [], array $estimates = []): void
{
    $answer = function (array &$queue) {
        $next = array_shift($queue) ?? [refusalBody('unexpected'), 500];

        if ($next === 'timeout') {
            throw new ConnectionException('timed out');
        }

        return isset($next[1]) && is_int($next[1]) ? Http::response($next[0], $next[1]) : Http::response($next);
    };

    $estimates = $estimates ?: [predictFixture('estimate-polymarket-pool')['body']];
    // Every bet body sent, timeouts included (Http::recorded() only keeps requests that got an answer).
    $GLOBALS['slip_bets_sent'] = [];

    Http::fake(function (Request $request) use ($market, &$quotes, &$bets, &$estimates, $answer) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/predict/quotes') => $answer($quotes),
            str_contains($url, '/predict/bets') => (function () use ($request, &$bets, $answer) {
                $GLOBALS['slip_bets_sent'][] = $request->data();

                return $answer($bets);
            })(),
            str_contains($url, '/estimate') => $answer($estimates),
            str_contains($url, "/predict/markets/{$market['id']}") => Http::response(['success' => true, 'data' => $market]),
            str_contains($url, '/wallet') || str_contains($url, '/customers/') => Http::response(['success' => true, 'data' => ['balance' => 800]]),
            default => Http::response([], 404),
        };
    });
}

/** @return list<array<string, mixed>> the bodies sent to POST …/predict/bets */
function betsSent(): array
{
    return $GLOBALS['slip_bets_sent'] ?? [];
}

function quotesSent(): int
{
    return collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/predict/quotes'))->count();
}

function slip(User $player, int $marketId = 7, ?string $side = 'yes')
{
    return Livewire::actingAs($player)->test(Slip::class, ['marketId' => $marketId, 'initialSide' => $side]);
}

// ── Who may bet ──────────────────────────────────────────────────────────

test('players who may not bet are told why and cannot quote or place', function (string $case, string $message) {
    fakeSlipApi(slipMarket(reference: true));

    match ($case) {
        'switch' => config(['platform.predictions_betting' => false]),
        'unverified' => $this->player->forceFill(['email_verified_at' => null])->save(),
        'unlinked' => $this->player->forceFill(['linked_id' => null])->save(),
        'break' => Cache::put('responsible_play_state_'.$this->player->id, ['break_ends_at' => now()->addDay()->toIso8601String()], 600),
    };

    if ($case === 'break') {
        $this->mock(ResponsiblePlay::class, fn ($mock) => $mock->shouldReceive('onBreak')->andReturnTrue());
    }

    slip($this->player->fresh())->assertSee($message)->set('stake', '100')->call('getQuote')->call('place');

    expect(quotesSent())->toBe(0)->and(betsSent())->toBe([]);
})->with([
    ['switch', 'Betting on predictions opens soon'],
    ['unverified', 'Verify your e-mail address'],
    ['unlinked', 'still being set up'],
    ['break', 'paused while your break'],
]);

test('a tester may bet while betting is closed to everyone else', function () {
    config(['platform.predictions_betting' => false, 'predict.betting_testers' => [$this->player->id]]);
    fakeSlipApi(slipMarket(), bets: [slipBetAnswer(estimate: true)]);

    slip($this->player)->set('stake', '100')->call('place')->assertSet('state', 'placed');
});

// ── Fixed odds: quote then bet ──────────────────────────────────────────

test('a fixed-odds bet: quote, countdown from the server, then placing with the quote and a fresh UUID reference', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote(), 'replayed' => false]]], bets: [slipBetAnswer()]);

    $slip = slip($this->player)->set('stake', '100')->call('getQuote')
        ->assertSee('Pays 1.58x')
        ->assertSee('KES 158.00')
        ->assertSeeHtml('x-data="{ left: 10 }"');

    $slip->call('place')
        ->assertSet('state', 'placed')
        ->assertSee('Bet placed')
        ->assertSee('at 1.58x (fixed)')
        ->assertDispatched('wallet-refreshed')
        ->assertDispatched('kadi-track', name: 'bet_placed', params: ['product' => 'predict', 'bet_type' => 'fixed']);

    $sent = betsSent()[0];
    expect($sent)->toMatchArray(['market_id' => 7, 'side' => 'yes', 'stake' => '100.00', 'quote_id' => 'q-1'])
        ->and($sent['reference'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and(Cache::get('wallet_balance_'.$this->player->id))->toEqual(900)
        ->and(PendingAttempt::get($this->player))->toBeNull();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'player', 'event' => 'predict_bet_placed']);
});

test('an expired quote is refused here without calling the API', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote()]]]);

    $slip = slip($this->player)->set('stake', '100')->call('getQuote');
    $this->travel(11)->seconds();
    $slip->call('place')->assertSee('That price has expired');

    expect(betsSent())->toBe([]);
});

test('changing the stake or the side drops the quote: it was for that stake and side only', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote()]]]);

    slip($this->player)->set('stake', '100')->call('getQuote')->assertNotSet('quote', null)
        ->set('stake', '150')->assertSet('quote', null);
});

test('a quote for something other than what was asked is never used', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote(stake: 999)]]]);

    slip($this->player)->set('stake', '100')->call('getQuote')->assertSet('quote', null)->assertSee('could not get a price');
});

test('a pool market that says quote_not_needed switches to placing without one', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [[refusalBody('quote_not_needed'), 409]], bets: [slipBetAnswer(estimate: true)]);

    slip($this->player)->set('stake', '100')->call('getQuote')->assertSet('poolFlow', true)
        ->call('place')->assertSet('state', 'placed');

    expect(betsSent()[0])->not->toHaveKey('quote_id');
});

// ── Pool: no quote ──────────────────────────────────────────────────────

test('a pool bet is placed directly, with the estimate shown first and marked estimated', function () {
    fakeSlipApi(slipMarket(), bets: [slipBetAnswer(estimate: true)]);

    slip($this->player)->set('stake', '100')
        ->assertSee('Estimated: 2.11x, KES 211.00')
        ->call('place')
        ->assertSee('(estimated)')
        ->assertDispatched('kadi-track', name: 'bet_placed', params: ['product' => 'predict', 'bet_type' => 'pool']);

    expect(quotesSent())->toBe(0)->and(betsSent()[0])->not->toHaveKey('quote_id');
});

// ── Unknown outcomes ────────────────────────────────────────────────────

test('no answer is asked once more with the same reference, then kept for "Try again", even across a reload', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote()]]], bets: ['timeout', [refusalBody('server_error'), 503], slipBetAnswer()]);

    $slip = slip($this->player)->set('stake', '100')->call('getQuote')->call('place')
        ->assertSet('state', 'checking')
        ->assertSee('checking your bet');

    [$first, $second] = betsSent();
    expect($second['reference'])->toBe($first['reference'])->and($second['quote_id'])->toBe('q-1')
        ->and(PendingAttempt::get($this->player))->toMatchArray(['reference' => $first['reference'], 'quote_id' => 'q-1', 'stake' => 100.0]);

    // A reload: a new slip for this market finds the attempt.
    $reloaded = slip($this->player, side: null)->assertSet('state', 'checking')->assertSet('side', 'yes')->assertSet('stake', '100');

    // The price expired meanwhile: "Try again" is still safe (the API checks the reference first).
    $this->travel(30)->seconds();
    $reloaded->call('retry')->assertSet('state', 'placed');

    expect(betsSent()[2]['reference'])->toBe($first['reference'])
        ->and(PendingAttempt::get($this->player))->toBeNull();
});

test('a 429 gets no retry in the request', function () {
    fakeSlipApi(slipMarket(), bets: [[['message' => 'Too Many Attempts.', 'code' => 'rate_limited'], 429]]);

    slip($this->player)->set('stake', '100')->call('place')->assertSet('state', 'checking');

    expect(betsSent())->toHaveCount(1);
});

test('a quote refusal on "Try again" means the bet was never placed', function () {
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote()]]], bets: ['timeout', 'timeout', [refusalBody('quote_expired'), 409]]);

    slip($this->player)->set('stake', '100')->call('getQuote')->call('place')->assertSet('state', 'checking')
        ->call('retry')
        ->assertSet('state', 'pick')
        ->assertSee('Your bet was not placed');

    expect(PendingAttempt::get($this->player))->toBeNull();
});

test('a replayed bet moved no money now: the wallet is read again and nothing is recorded twice', function () {
    fakeSlipApi(slipMarket(), bets: [slipBetAnswer(replayed: true)]);
    $fetcher = $this->mock(WalletBalanceFetcher::class);
    $fetcher->shouldReceive('refresh')->once()->andReturn(['balance' => 900.0, 'fresh' => true]);

    slip($this->player)->set('stake', '100')->call('place')->assertSet('state', 'placed')->assertNotDispatched('kadi-track');

    $this->assertDatabaseMissing('activity_log', ['event' => 'predict_bet_placed']);
});

test('one placement per player at a time', function () {
    fakeSlipApi(slipMarket());
    Cache::lock('predict-bet:'.$this->player->id, 30)->get();

    slip($this->player)->set('stake', '100')->call('place')->assertSee('Another bet of yours is being placed');

    expect(betsSent())->toBe([]);
});

test('a bet still being checked on another market blocks new bets until it is resolved', function () {
    fakeSlipApi(slipMarket());
    PendingAttempt::put($this->player, ['reference' => 'r-1', 'market_id' => 99, 'side' => 'no', 'stake' => 50.0, 'quote_id' => null]);

    slip($this->player)->set('stake', '100')->call('place')->assertSee('another market')->assertSeeHtml('/predictions/99');

    expect(betsSent())->toBe([])->and(PendingAttempt::get($this->player)['market_id'])->toBe(99);
});

// ── Price changes and refusals ──────────────────────────────────────────

test('the price moved: the new figure is shown, and accepting places with the new quote and a new reference', function () {
    fakeSlipApi(
        slipMarket(reference: true),
        quotes: [['success' => true, 'data' => ['quote' => slipQuote()]]],
        bets: [[refusalBody('price_changed', 'Moved.', ['multiplier' => 1.53, 'quote' => slipQuote(multiplier: 1.53, id: 'q-2')]), 409], slipBetAnswer()],
    );

    $slip = slip($this->player)->set('stake', '100')->call('getQuote')->call('place')
        ->assertSee('Odds changed: now 1.53x')
        ->call('acceptChange')
        ->assertSet('state', 'placed');

    [$first, $second] = betsSent();
    expect($second['quote_id'])->toBe('q-2')->and($second['reference'])->not->toBe($first['reference']);
});

test('refusals say what happened and offer the one next step', function (array $body, string $see, string $action) {
    fakeSlipApi(slipMarket(), bets: [[$body, $body['code'] === 'insufficient_funds' ? 402 : 409]]);

    slip($this->player)->set('stake', '100')->call('place')->assertSet('state', 'pick')->assertSee($see)->assertSeeHtml($action);

    expect(PendingAttempt::get($this->player))->toBeNull();
})->with([
    'no money' => [refusalBody('insufficient_funds'), 'Your balance is too low', "\$dispatch('open-deposit')"],
    'pool side full' => [refusalBody('pool_side_full', 'Full.', ['max_stake' => 40]), 'This side can take up to KES 40.00', 'useStake(40)'],
    'other side' => [refusalBody('odds_unavailable'), 'You can still bet the other side', "pick('no')"],
    'player limit' => [refusalBody('customer_limit', 'Limit.', ['remaining' => 250]), 'up to KES 250.00 more', 'useStake(250)'],
]);

test('a refusal without a code shows the API\'s message', function () {
    fakeSlipApi(slipMarket(), bets: [[['success' => false, 'message' => 'The stake field is required.'], 422]]);

    slip($this->player)->set('stake', '100')->call('place')->assertSee('The stake field is required.');
});

test('the stake must sit within the market\'s limits, checked before any call', function () {
    fakeSlipApi(slipMarket());

    slip($this->player)->set('stake', '5')->call('place')->assertSee('The least you can stake is KES 10.00')
        ->set('stake', '9000')->call('place')->assertSee('The most you can stake is KES 5,000.00')
        ->set('stake', '10.555')->call('place')->assertSee('to the cent');

    expect(betsSent())->toBe([]);
});

test('past the betting cut-off nothing is sent', function () {
    fakeSlipApi(slipMarket(changes: ['betting_closes_at' => '2026-11-13T14:40:00+03:00']));

    $slip = slip($this->player)->set('stake', '100');
    $this->travelTo(Date::parse('2026-11-13T14:40:01+03:00'));
    Cache::flush();
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
    $slip->call('place')->assertSee('Betting closed');

    expect(betsSent())->toBe([]);
});

// ── Estimates ───────────────────────────────────────────────────────────

test('estimates are limited per player and for the whole site, and a 429 keeps the last figure', function () {
    fakeSlipApi(slipMarket(), estimates: [
        predictFixture('estimate-polymarket-pool')['body'],
        [['message' => 'Too Many Attempts.'], 429],
    ]);

    $slip = slip($this->player)->set('stake', '100')->assertSee('2.11x')
        ->set('stake', '120')->assertSee('2.11x');

    config(['predict.limits.estimates_site' => 2]);
    $slip->set('stake', '130');

    $estimates = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/estimate'))->count();
    expect($estimates)->toBe(2);
});

// ── Elsewhere ───────────────────────────────────────────────────────────

test('closing an account explains open prediction bets', function () {
    expect(CloseAccount::REASONS)->toHaveKey('open_prediction_bet');
});

test('references, quote ids and stakes never reach the log', function () {
    Log::spy();
    fakeSlipApi(slipMarket(reference: true), quotes: [['success' => true, 'data' => ['quote' => slipQuote(id: 'quote-secret')]]], bets: ['timeout', 'timeout']);

    slip($this->player)->set('stake', '4321')->call('getQuote');
    PendingAttempt::forget($this->player);
    slip($this->player)->set('stake', '100')->call('getQuote');

    Log::shouldNotHaveReceived('info', fn ($message, $context = []) => str_contains(json_encode($context), 'quote-secret') || str_contains(json_encode($context), '4321'));
    Log::shouldNotHaveReceived('warning', fn ($message, $context = []) => str_contains(json_encode($context), 'quote-secret') || str_contains(json_encode($context), '4321'));
});
