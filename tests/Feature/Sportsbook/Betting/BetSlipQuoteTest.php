<?php

use App\Sportsbook\Betting\BetSlipQuote;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Enums\SuspensionReason;

beforeEach(function () {
    config(['sportsbook.limits' => ['min_stake' => 10, 'max_stake' => 20000, 'max_payout' => 1000000, 'max_selections' => 30]]);
});

function quote(array $picks, string $stake = ''): BetSlipQuote
{
    return BetSlipQuote::for($picks, $stake, app(BettableSelections::class));
}

test('multiplies current prices into the combined odds, rounding down', function () {
    $first = selectionFor(pricedFixture(prices: ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']));
    $second = selectionFor(pricedFixture(prices: ['home' => '1.85', 'draw' => '3.40', 'away' => '4.20']));

    $quote = quote([slipPick($first), slipPick($second)], '100');

    expect($quote->totalOdds)->toBe(3.88)
        ->and($quote->possibleWin)->toBe(388.0)
        ->and($quote->bettableCount())->toBe(2);
});

test('flags a pick whose price changed and prices the slip at the current price', function () {
    $selection = selectionFor(pricedFixture(prices: ['home' => '2.40', 'draw' => '3.40', 'away' => '3.50']));

    $quote = quote([slipPick($selection, '2.10')], '100');

    expect($quote->lines[0]['status'])->toBe('price_changed')
        ->and($quote->changedPrices())->toBe([$selection->id => '2.40'])
        ->and($quote->totalOdds)->toBe(2.4);
});

test('marks suspended and kicked-off picks unavailable and leaves them out of the odds', function () {
    $open = selectionFor(pricedFixture(prices: ['home' => '2.00', 'draw' => '3.40', 'away' => '3.50']));
    $suspended = selectionFor(pricedFixture());
    $suspended->market->suspend(SuspensionReason::Manual);
    $kickedOff = selectionFor(pricedFixture(['kicks_off_at' => now()->subMinute()]));

    $quote = quote([slipPick($open), slipPick($suspended->fresh()), slipPick($kickedOff)], '100');

    expect(array_column($quote->lines, 'status'))->toBe(['ok', 'unavailable', 'unavailable'])
        ->and(array_column($quote->lines, 'message'))->toBe([null, 'This market is suspended.', 'This match has started.'])
        ->and($quote->totalOdds)->toBe(2.0);
});

test('keeps one pick per match even if the browser sends two', function () {
    $fixture = pricedFixture();

    $quote = quote([slipPick(selectionFor($fixture, 'home')), slipPick(selectionFor($fixture, 'draw'))]);

    expect($quote->lines)->toHaveCount(1);
});

test('ignores malformed and unknown picks', function () {
    $selection = selectionFor(pricedFixture());

    $quote = quote([slipPick($selection), ['selection_id' => 'abc'], 'junk', ['selection_id' => 999999, 'price' => '2.00']]);

    expect($quote->lines)->toHaveCount(1);
});

test('rejects stakes outside the limits', function (string $stake, string $error) {
    $selection = selectionFor(pricedFixture());

    expect(quote([slipPick($selection)], $stake)->stakeErrors)->toBe([$error]);
})->with([
    'below the minimum' => ['5', 'The minimum stake is KES 10.'],
    'above the maximum' => ['20001', 'The maximum stake is KES 20,000.'],
    'not a number' => ['ten', 'Enter your stake as a number.'],
]);

test('caps the possible win at the maximum payout', function () {
    $picks = collect(range(1, 4))->map(fn () => slipPick(selectionFor(pricedFixture(prices: ['home' => '40.00', 'draw' => '3.40', 'away' => '1.05']))))->all();

    $quote = quote($picks, '20000');

    expect($quote->possibleWin)->toBe(1000000.0)
        ->and($quote->isPayoutCapped)->toBeTrue();
});

test('only considers the configured maximum number of picks', function () {
    config(['sportsbook.limits.max_selections' => 2]);
    $picks = collect(range(1, 3))->map(fn () => slipPick(selectionFor(pricedFixture())))->all();

    expect(quote($picks)->lines)->toHaveCount(2);
});
