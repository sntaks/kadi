<?php

use App\Livewire\Predict\MyBets;
use App\Livewire\Predict\Slip;
use App\Predict\MarketFeed;
use App\Predict\PendingAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

/**
 * Money end to end against a stateful fake of the API's prediction routes (tests/Support/FakePredictApi.php): a
 * bet moves the player's money once and only once, whatever happens to the answers on the way.
 */
beforeEach(function () {
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
    $this->api = predictApi();
    $this->player = $this->api->player(cents: 50_000);
});

function placeOn(int $marketId, string $side = 'yes', string $stake = '100', bool $quote = false)
{
    $slip = Livewire::actingAs(test()->player)->test(Slip::class, ['marketId' => $marketId, 'initialSide' => $side])->set('stake', $stake);

    if ($quote) {
        $slip->call('getQuote');
    }

    return $slip->call('place');
}

test('a fixed-odds bet takes the stake once, and the site shows the API\'s balance', function () {
    $this->api->fixedMarket(8);

    placeOn(8, quote: true)->assertSet('state', 'placed')->assertSee('at 1.58x (fixed)');

    expect($this->api->balanceOf($this->player))->toBe(40_000)
        ->and(Cache::get('wallet_balance_'.$this->player->id))->toEqual(400)
        ->and($this->api->betsOf($this->player))->toHaveCount(1);

    Livewire::actingAs($this->player)->test(MyBets::class)->assertSee('Fake question 8?')->assertSee('1.58x · Fixed odds');
});

test('the API placed it but the answer was lost twice: "Try again" replays it, and the money moved once', function () {
    $this->api->fixedMarket(8)->next('bet', 'lost-answer', 'timeout');

    $slip = placeOn(8, quote: true)->assertSet('state', 'checking');

    expect($this->api->balanceOf($this->player))->toBe(40_000)
        ->and(PendingAttempt::get($this->player))->not->toBeNull();

    // Long after the quote expired: the API checks the reference first and answers with the bet it already has.
    $this->travel(2)->minutes();
    $slip->call('retry')->assertSet('state', 'placed');

    $references = array_unique(array_column($this->api->callsTo('bet'), 'reference'));
    expect($this->api->balanceOf($this->player))->toBe(40_000)
        ->and($this->api->betsOf($this->player))->toHaveCount(1)
        ->and($references)->toHaveCount(1)
        ->and(PendingAttempt::get($this->player))->toBeNull()
        // A replay carries no balance: the wallet was read again.
        ->and(Cache::get('wallet_balance_'.$this->player->id))->toEqual(400);
});

test('no answer at all, then "Try again": the bet is placed once', function () {
    $this->api->poolMarket(7)->next('bet', 'timeout', 'timeout');

    $slip = placeOn(7)->assertSet('state', 'checking');
    expect($this->api->balanceOf($this->player))->toBe(50_000);

    $slip->call('retry')->assertSet('state', 'placed');
    expect($this->api->balanceOf($this->player))->toBe(40_000)->and($this->api->betsOf($this->player))->toHaveCount(1);
});

test('a quote is used once: the next bet needs a new one', function () {
    $this->api->fixedMarket(8);

    $slip = placeOn(8, quote: true)->assertSet('state', 'placed');
    $slip->call('another')->call('pick', 'yes')->set('stake', '100')->call('place')->assertSee('Get a price first');

    expect($this->api->balanceOf($this->player))->toBe(40_000);
});

test('without enough money nothing is taken, and the deposit sheet is offered', function () {
    $this->api->poolMarket(7);
    $poor = $this->api->player(cents: 5_000);
    $this->player = $poor;

    placeOn(7)->assertSee('Your balance is too low')->assertSeeHtml("\$dispatch('open-deposit')");

    expect($this->api->balanceOf($poor))->toBe(5_000)->and($this->api->betsOf($poor))->toBe([]);
});

test('a long-shot pool now pays above 1x on both sides, and the favourite can be bet', function () {
    // 5% Yes: the favourite (No) pays 1 + 50 × 0.8 / 950 = 1.042x under the API's rule since PR #63.
    $this->api->poolMarket(7, yesShare: 0.05);

    Livewire::actingAs($this->player)->test(Slip::class, ['marketId' => 7])
        ->assertSee('1.04x')
        ->assertDontSeeHtml('>Pays less than your stake<');

    placeOn(7, side: 'no')->assertSet('state', 'placed')->assertSee('(estimated)');

    expect($this->api->balanceOf($this->player))->toBe(40_000);
});

test('a pool estimate is the API\'s figure for that stake', function () {
    $this->api->poolMarket(7, yesShare: 0.5);

    // KES 100 on Yes in a 500/500 pool: 1 + 500 × 0.8 / 600 = 1.66x.
    Livewire::actingAs($this->player)->test(Slip::class, ['marketId' => 7, 'initialSide' => 'yes'])->set('stake', '100')
        ->assertSee('Estimated: 1.66x, KES 166.00');
});
