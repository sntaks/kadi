<?php

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Pricing\Derived\DerivedMarket;
use App\Sportsbook\Pricing\Derived\DerivedMarketPricer;

function derivedMarkets(array $fair = ['home' => 0.5, 'draw' => 0.25, 'away' => 0.25]): array
{
    $pricer = new DerivedMarketPricer(margin: 0.06, modelMargin: 0.10, firstHalfShare: 0.45, maximumModelPrice: 500.0);

    return $pricer->price($fair, 'Arsenal', 'Chelsea', bookmakerCount: 12);
}

function derivedMarket(MarketType $type, ?float $line = null): DerivedMarket
{
    return collect(derivedMarkets())->first(fn (DerivedMarket $market) => $market->type === $type && $market->line === $line);
}

test('prices double chance straight from the 1X2 at the standard margin', function () {
    $market = derivedMarket(MarketType::DoubleChance);

    expect(array_column($market->priced->outcomes, 'price'))->toBe(['1.25', '1.25', '1.88'])
        ->and($market->selectionNames)->toBe(['home_draw' => 'Arsenal or Draw', 'home_away' => 'Arsenal or Chelsea', 'draw_away' => 'Draw or Chelsea']);
});

test('prices draw no bet from home and away only, at the standard margin', function () {
    $market = derivedMarket(MarketType::DrawNoBet);

    expect($market->priced->outcomes)->toBe([
        'home' => ['fair_probability' => 0.666667, 'price' => '1.41'],
        'away' => ['fair_probability' => 0.333333, 'price' => '2.83'],
    ]);
});

test('offers over/under 1.5, 2.5 and 3.5 from the goals model', function () {
    $lines = collect(derivedMarkets())->filter(fn (DerivedMarket $market) => $market->type === MarketType::Totals)->pluck('line')->all();

    expect($lines)->toBe([1.5, 2.5, 3.5]);
});

test('model-priced markets cover every outcome and carry at least the higher margin', function (MarketType $type) {
    $market = derivedMarket($type, $type === MarketType::Totals ? 2.5 : null);

    $fairTotal = array_sum(array_column($market->priced->outcomes, 'fair_probability'));
    $book = array_sum(array_map(fn (array $outcome) => 1 / (float) $outcome['price'], $market->priced->outcomes));

    expect(abs($fairTotal - 1))->toBeLessThan(0.0001)
        ->and($book)->toBeGreaterThanOrEqual(1.10);
})->with([
    'both teams to score' => MarketType::BothTeamsToScore,
    'over/under 2.5' => MarketType::Totals,
    'correct score' => MarketType::CorrectScore,
    'half-time/full-time' => MarketType::HalfTimeFullTime,
]);

test('lists every score up to 4-4 plus any other score', function () {
    $market = derivedMarket(MarketType::CorrectScore);

    expect(count($market->selectionNames))->toBe(26)
        ->and($market->selectionNames['other'])->toBe('Any other score');
});

test('caps very long model prices', function () {
    $market = derivedMarket(MarketType::CorrectScore);

    expect((float) $market->priced->outcomes['0-4']['price'])->toBeLessThanOrEqual(500.0)
        ->and((float) $market->priced->outcomes['4-4']['price'])->toBe(500.0);
});
