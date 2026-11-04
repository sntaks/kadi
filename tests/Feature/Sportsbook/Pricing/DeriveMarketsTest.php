<?php

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Pricing\Derived\DeriveMarkets;

function fixtureWithPricedMatchResult(): Fixture
{
    $fixture = Fixture::factory()->create(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);
    $matchResult = Market::factory()->for($fixture)->create(['bookmaker_count' => 12]);

    foreach (['home' => '0.500000', 'draw' => '0.250000', 'away' => '0.250000'] as $outcome => $probability) {
        Selection::factory()->for($matchResult)->create(['outcome' => $outcome, 'fair_probability' => $probability]);
    }

    return $fixture;
}

test('creates every derived market from a priced 1X2 and opens them', function () {
    $fixture = fixtureWithPricedMatchResult();

    app(DeriveMarkets::class)->handle($fixture);

    $derived = $fixture->markets()->where('source', MarketSource::Derived)->get();
    expect($derived->pluck('key')->sort()->values()->all())->toBe([
        'btts', 'correct_score', 'double_chance', 'draw_no_bet', 'ht_ft', 'totals:1.5', 'totals:2.5', 'totals:3.5',
    ])
        ->and($derived->every(fn (Market $market) => $market->status === MarketStatus::Open))->toBeTrue()
        ->and($derived->every(fn (Market $market) => $market->bookmaker_count === 12))->toBeTrue();
});

test('leaves a goal line the feed prices to the feed', function () {
    $fixture = fixtureWithPricedMatchResult();
    $feedLine = Market::factory()->for($fixture)->create(['type' => MarketType::Totals, 'line' => 2.5, 'key' => 'totals:2.5']);
    Selection::factory()->for($feedLine)->create(['outcome' => 'over', 'feed_price' => '1.95']);

    app(DeriveMarkets::class)->handle($fixture);

    expect($feedLine->fresh()->source)->toBe(MarketSource::Feed)
        ->and($feedLine->selections()->where('outcome', 'over')->value('feed_price'))->toBe('1.95');
});

test('suspends derived markets when the 1X2 can no longer be priced', function () {
    $fixture = fixtureWithPricedMatchResult();
    app(DeriveMarkets::class)->handle($fixture);
    $fixture->matchResultMarket->selections()->update(['fair_probability' => null]);

    app(DeriveMarkets::class)->handle($fixture);

    $statuses = $fixture->markets()->where('source', MarketSource::Derived)->pluck('suspension_reason')->unique()->values()->all();
    expect($statuses)->toBe([SuspensionReason::InsufficientBookmakers]);
});

test('keeps an admin\'s manual suspension of a derived market', function () {
    $fixture = fixtureWithPricedMatchResult();
    app(DeriveMarkets::class)->handle($fixture);
    $correctScore = $fixture->markets()->where('key', 'correct_score')->sole();
    $correctScore->suspend(SuspensionReason::Manual);

    app(DeriveMarkets::class)->handle($fixture);

    expect($correctScore->fresh()->status)->toBe(MarketStatus::Suspended);
});
