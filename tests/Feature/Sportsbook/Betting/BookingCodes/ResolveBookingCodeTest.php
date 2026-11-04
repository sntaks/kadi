<?php

use App\Sportsbook\Betting\BookingCodes\ResolveBookingCode;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\BookingCode;

test('returns the picks for a booking code at current prices, whatever case it is typed in', function () {
    $selection = selectionFor(pricedFixture(prices: ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']));
    BookingCode::factory()->create(['code' => 'K7Q2ZP', 'selection_ids' => [$selection->id]]);

    $resolved = app(ResolveBookingCode::class)->handle(' k7q2zp ');

    expect($resolved)->toBe([
        'picks' => [['fixture_id' => $selection->market->fixture_id, 'selection_id' => $selection->id, 'price' => '2.10']],
        'unavailable' => [],
    ]);
});

test('lists selections that can no longer be bet on instead of adding them', function () {
    $open = selectionFor(pricedFixture());
    $closed = selectionFor(pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']));
    $closed->market->suspend(SuspensionReason::Manual);
    BookingCode::factory()->create(['code' => 'K7Q2ZP', 'selection_ids' => [$open->id, $closed->id]]);

    $resolved = app(ResolveBookingCode::class)->handle('K7Q2ZP');

    expect($resolved['picks'])->toHaveCount(1)
        ->and($resolved['unavailable'])->toBe(['Arsenal v Chelsea: 1X2 Arsenal']);
});

test('rejects unknown and expired codes', function (Closure $makeCode, string $message) {
    $makeCode();

    expect(fn () => app(ResolveBookingCode::class)->handle('K7Q2ZP'))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown' => [fn () => null, 'That booking code does not exist.'],
    'expired' => [fn () => BookingCode::factory()->expired()->create(['code' => 'K7Q2ZP']), 'That booking code has expired'],
]);
