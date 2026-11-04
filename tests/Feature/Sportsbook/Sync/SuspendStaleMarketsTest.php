<?php

use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Sync\SuspendStaleMarkets;

beforeEach(function () {
    config(['sportsbook.odds.stale_after_hours' => 26]);
});

test('suspends open markets whose fixture has kicked off', function () {
    $market = Market::factory()->for(Fixture::factory()->kickedOff())->create();

    $suspended = app(SuspendStaleMarkets::class)->handle();

    expect($suspended)->toBe(['kicked_off' => 1, 'stale' => 0])
        ->and($market->fresh()->suspension_reason)->toBe(SuspensionReason::KickedOff);
});

test('suspends open markets whose odds are older than the stale window', function () {
    $stale = Market::factory()->pricedHoursAgo(27)->create();
    $fresh = Market::factory()->pricedHoursAgo(25)->create();

    $suspended = app(SuspendStaleMarkets::class)->handle();

    expect($suspended)->toBe(['kicked_off' => 0, 'stale' => 1])
        ->and($stale->fresh()->suspension_reason)->toBe(SuspensionReason::StaleOdds)
        ->and($fresh->fresh()->status)->toBe(MarketStatus::Open);
});

test('leaves already suspended markets and their reason alone', function () {
    $market = Market::factory()->for(Fixture::factory()->kickedOff())->suspended(SuspensionReason::Manual)->create();

    app(SuspendStaleMarkets::class)->handle();

    expect($market->fresh()->suspension_reason)->toBe(SuspensionReason::Manual);
});
