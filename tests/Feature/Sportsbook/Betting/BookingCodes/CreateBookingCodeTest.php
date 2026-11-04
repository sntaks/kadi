<?php

use App\Models\User;
use App\Sportsbook\Betting\BookingCodes\CreateBookingCode;
use App\Sportsbook\Enums\SuspensionReason;

test('saves the bettable selections under a short code that expires at the first kick-off', function () {
    $this->freezeSecond();
    $later = selectionFor(pricedFixture(['kicks_off_at' => now()->addDays(3)]));
    $sooner = selectionFor(pricedFixture(['kicks_off_at' => now()->addDay()]));
    $user = User::factory()->create();

    $bookingCode = app(CreateBookingCode::class)->handle([$later->id, $sooner->id], $user);

    expect($bookingCode->code)->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{6}$/')
        ->and($bookingCode->selection_ids)->toEqualCanonicalizing([$later->id, $sooner->id])
        ->and($bookingCode->expires_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($bookingCode->createdBy->is($user))->toBeTrue();
});

test('leaves out selections that cannot be bet on and keeps one per match', function () {
    $fixture = pricedFixture();
    $suspended = selectionFor(pricedFixture());
    $suspended->market->suspend(SuspensionReason::Manual);

    $bookingCode = app(CreateBookingCode::class)->handle([selectionFor($fixture, 'home')->id, selectionFor($fixture, 'draw')->id, $suspended->id]);

    expect($bookingCode->selection_ids)->toHaveCount(1);
});

test('refuses to save a slip with nothing open for betting', function () {
    $suspended = selectionFor(pricedFixture());
    $suspended->market->suspend(SuspensionReason::Manual);

    expect(fn () => app(CreateBookingCode::class)->handle([$suspended->id]))
        ->toThrow(InvalidArgumentException::class, 'Add at least one selection that is open for betting.');

    $this->assertDatabaseCount('sb_booking_codes', 0);
});
