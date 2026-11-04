<?php

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Trading\SuspendMarket;
use Spatie\Activitylog\Models\Activity;

test('suspends a market as a manual suspension and logs who did it and why', function () {
    $admin = User::factory()->create();
    $market = Market::factory()->create();

    app(SuspendMarket::class)->handle($market, $admin, 'Possible match fixing report');

    $activity = Activity::query()->where('event', 'market-suspended')->sole();
    expect($market->fresh()->status)->toBe(MarketStatus::Suspended)
        ->and($market->fresh()->suspension_reason)->toBe(SuspensionReason::Manual)
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties->get('reason'))->toBe('Possible match fixing report');
});

test('suspending a 1X2 also suspends the markets derived from it', function () {
    $matchResult = Market::factory()->create();
    $derived = Market::factory()->for($matchResult->fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    $feedLine = Market::factory()->for($matchResult->fixture)->create(['type' => MarketType::Totals, 'line' => 2.5, 'key' => 'totals:2.5']);

    app(SuspendMarket::class)->handle($matchResult, User::factory()->create(), 'Late team news');

    expect($derived->fresh()->suspension_reason)->toBe(SuspensionReason::Manual)
        ->and($feedLine->fresh()->status)->toBe(MarketStatus::Open);
});
