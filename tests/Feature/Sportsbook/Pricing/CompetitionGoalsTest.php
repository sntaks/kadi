<?php

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Pricing\Derived\CompetitionGoals;

function fixtureWithFeedOverTwoAndAHalf(Competition $competition, string $fairOver): void
{
    $market = Market::factory()->for(Fixture::factory()->for($competition))->create(['type' => MarketType::Totals, 'line' => 2.5, 'key' => 'totals:2.5']);
    Selection::factory()->for($market)->create(['outcome' => 'over', 'fair_probability' => $fairOver]);
}

test('uses the median expected goals implied by the competition\'s feed Over 2.5 prices', function () {
    config(['sportsbook.odds.default_total_goals' => 2.7]);
    $competition = Competition::factory()->create();
    fixtureWithFeedOverTwoAndAHalf($competition, '0.400000');
    fixtureWithFeedOverTwoAndAHalf($competition, '0.500000');
    fixtureWithFeedOverTwoAndAHalf($competition, '0.700000');

    expect(round(app(CompetitionGoals::class)->forCompetition($competition), 2))->toBe(2.67);
});

test('falls back to the configured expected goals when the competition has no feed Over 2.5 prices', function () {
    config(['sportsbook.odds.default_total_goals' => 2.55]);

    expect(app(CompetitionGoals::class)->forCompetition(Competition::factory()->create()))->toBe(2.55);
});
