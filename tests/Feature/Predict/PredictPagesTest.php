<?php

use App\Models\User;
use App\Support\PredictPages;

test('betting follows the two switches', function (bool $pages, bool $betting, bool $expected) {
    config(['platform.predictions' => $pages, 'platform.predictions_betting' => $betting]);

    expect(PredictPages::on())->toBe($pages)
        ->and(PredictPages::bettingOn())->toBe($expected)
        ->and(PredictPages::bettingFor(User::factory()->create()))->toBe($expected)
        ->and(PredictPages::bettingFor(null))->toBe($expected);
})->with([
    'both on' => [true, true, true],
    'betting off' => [true, false, false],
    'pages off' => [false, true, false],
    'both off' => [false, false, false],
]);

test('a tester can bet while betting is closed, and nobody else can', function () {
    $tester = User::factory()->create();
    $player = User::factory()->create();

    config(['platform.predictions_betting' => false, 'predict.betting_testers' => [$tester->id]]);

    expect(PredictPages::bettingFor($tester))->toBeTrue()
        ->and(PredictPages::bettingFor($player))->toBeFalse()
        ->and(PredictPages::bettingFor(null))->toBeFalse()
        ->and(PredictPages::bettingOn())->toBeFalse();
});

test('a tester cannot bet while the pages are off', function () {
    $tester = User::factory()->create();

    config(['platform.predictions' => false, 'platform.predictions_betting' => false, 'predict.betting_testers' => [$tester->id]]);

    expect(PredictPages::bettingFor($tester))->toBeFalse();
});

test('the testers list is empty unless set, and reads comma-separated ids', function () {
    expect(config('predict.betting_testers'))->toBe([]);

    putenv('PREDICT_BETTING_TESTERS=12, 7,,x');
    $_ENV['PREDICT_BETTING_TESTERS'] = $_SERVER['PREDICT_BETTING_TESTERS'] = '12, 7,,x';

    try {
        expect((require config_path('predict.php'))['betting_testers'])->toBe([12, 7]);
    } finally {
        putenv('PREDICT_BETTING_TESTERS=');
        $_ENV['PREDICT_BETTING_TESTERS'] = $_SERVER['PREDICT_BETTING_TESTERS'] = '';
    }
});
