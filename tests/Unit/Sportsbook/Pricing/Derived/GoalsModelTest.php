<?php

use App\Sportsbook\Pricing\Derived\GoalsModel;

test('reproduces the 1X2 it was fitted to', function (float $home, float $draw, float $away) {
    $model = GoalsModel::fit($home, $draw, $away);

    expect(abs($model->homeProbability() - $home))->toBeLessThan(0.001)
        ->and(abs($model->drawProbability() - $draw))->toBeLessThan(0.001)
        ->and(abs($model->awayProbability() - $away))->toBeLessThan(0.001);
})->with([
    'even match' => [0.37, 0.27, 0.36],
    'home favourite' => [0.62, 0.22, 0.16],
    'heavy away favourite' => [0.10, 0.16, 0.74],
]);

test('keeps the expected goals it was given however likely a draw is', function () {
    $model = GoalsModel::fit(0.36, 0.30, 0.34, totalGoals: 2.7);

    expect(round($model->homeRate + $model->awayRate, 6))->toBe(2.7);
});

test('matches the 1X2 and an Over 2.5 probability at the same time', function () {
    $model = GoalsModel::fit(0.45, 0.27, 0.28, overTwoAndAHalf: 0.55);

    expect(abs($model->overProbability(2.5) - 0.55))->toBeLessThan(0.001)
        ->and(abs($model->drawProbability() - 0.27))->toBeLessThan(0.001)
        ->and(abs($model->homeProbability() - 0.45))->toBeLessThan(0.001);
});

test('turns an Over 2.5 probability into expected goals', function () {
    expect(round(GoalsModel::totalGoalsForOverTwoAndAHalf(0.5), 2))->toBe(2.67);
});

test('gives more goals to the stronger team', function () {
    $model = GoalsModel::fit(0.62, 0.22, 0.16);

    expect($model->homeRate)->toBeGreaterThan($model->awayRate);
});

test('half-time/full-time outcomes cover every result exactly once', function () {
    $outcomes = GoalsModel::fit(0.45, 0.27, 0.28)->halfTimeFullTime(0.45);

    expect(array_keys($outcomes))->toBe(['1/1', '1/X', '1/2', 'X/1', 'X/X', 'X/2', '2/1', '2/X', '2/2'])
        ->and(abs(array_sum($outcomes) - 1))->toBeLessThan(0.000001);
});
