<?php

use App\Sportsbook\Support\Money;
use Tests\TestCase;

uses(TestCase::class);

test('formats cents as shillings', function (int $cents, string $formatted) {
    expect(Money::format($cents))->toBe($formatted);
})->with([
    'whole' => [50_000, 'KES 500.00'],
    'with cents and thousands' => [125_050, 'KES 1,250.50'],
    'negative' => [-2_500, '-KES 25.00'],
    'zero' => [0, 'KES 0.00'],
]);

test('converts between shillings and cents', function () {
    expect(Money::fromShillings(500))->toBe(50_000)
        ->and(Money::fromAmount('250.00'))->toBe(25_000)
        ->and(Money::fromAmount(1.1))->toBe(110)
        ->and(Money::toShillings(50_099))->toBe(500);
});
