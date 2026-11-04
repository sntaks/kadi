<?php

use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Pricing\ConsensusPricer;

const MATCH_RESULT = ['home', 'draw', 'away'];

test('removes each bookmaker\'s margin and applies ours, rounding prices down', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);

    $priced = $pricer->price([
        'fair' => ['home' => 2.00, 'draw' => 4.00, 'away' => 4.00],
        'five-percent' => ['home' => 1.90, 'draw' => 3.80, 'away' => 3.80],
        'eleven-percent' => ['home' => 1.80, 'draw' => 3.60, 'away' => 3.60],
    ], MATCH_RESULT);

    expect($priced->isPriced())->toBeTrue()
        ->and($priced->bookmakerCount)->toBe(3)
        ->and($priced->outcomes)->toBe([
            'home' => ['fair_probability' => 0.5, 'price' => '1.88'],
            'draw' => ['fair_probability' => 0.25, 'price' => '3.77'],
            'away' => ['fair_probability' => 0.25, 'price' => '3.77'],
        ]);
});

test('ignores a single outlying bookmaker by using the median', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);

    $priced = $pricer->price([
        'a' => ['home' => 2.00, 'draw' => 4.00, 'away' => 4.00],
        'b' => ['home' => 2.00, 'draw' => 4.00, 'away' => 4.00],
        'outlier' => ['home' => 1.25, 'draw' => 10.00, 'away' => 10.00],
    ], MATCH_RESULT);

    expect(array_column($priced->outcomes, 'price'))->toBe(['1.88', '3.77', '3.77']);
});

test('averages the two middle bookmakers when there is an even number', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);

    $priced = $pricer->price([
        'a' => ['over' => 2.50, 'under' => 1 / 0.6],
        'b' => ['over' => 2.00, 'under' => 2.00],
        'c' => ['over' => 2.00, 'under' => 2.00],
        'd' => ['over' => 1 / 0.6, 'under' => 2.50],
    ], ['over', 'under']);

    expect($priced->outcomes)->toBe([
        'over' => ['fair_probability' => 0.5, 'price' => '1.88'],
        'under' => ['fair_probability' => 0.5, 'price' => '1.88'],
    ]);
});

test('never offers a book worth less than our margin', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);

    $priced = $pricer->price([
        'a' => ['home' => 2.62, 'draw' => 3.35, 'away' => 2.95],
        'b' => ['home' => 2.70, 'draw' => 3.25, 'away' => 2.80],
        'c' => ['home' => 2.55, 'draw' => 3.40, 'away' => 3.00],
    ], MATCH_RESULT);

    $bookPercentage = array_sum(array_map(fn (array $outcome): float => 1 / (float) $outcome['price'], $priced->outcomes));

    expect($bookPercentage)->toBeGreaterThanOrEqual(1.06);
});

test('floors very short prices at 1.01', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);
    $heavyFavourite = ['home' => 1 / 0.99, 'draw' => 200.0, 'away' => 200.0];

    $priced = $pricer->price(['a' => $heavyFavourite, 'b' => $heavyFavourite, 'c' => $heavyFavourite], MATCH_RESULT);

    expect($priced->outcomes['home']['price'])->toBe('1.01');
});

test('suspends the market when too few bookmakers price every outcome', function () {
    $pricer = new ConsensusPricer(margin: 0.06, minimumBookmakers: 3);

    $priced = $pricer->price([
        'a' => ['home' => 2.00, 'draw' => 4.00, 'away' => 4.00],
        'b' => ['home' => 2.00, 'draw' => 4.00, 'away' => 4.00],
        'missing-draw' => ['home' => 2.00, 'away' => 4.00],
        'invalid-price' => ['home' => 1.00, 'draw' => 4.00, 'away' => 4.00],
    ], MATCH_RESULT);

    expect($priced->isPriced())->toBeFalse()
        ->and($priced->suspensionReason)->toBe(SuspensionReason::InsufficientBookmakers)
        ->and($priced->bookmakerCount)->toBe(2)
        ->and($priced->outcomes)->toBe([]);
});
