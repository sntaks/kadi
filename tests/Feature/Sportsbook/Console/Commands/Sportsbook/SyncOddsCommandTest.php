<?php

use App\Jobs\Sportsbook\SyncCompetitionOdds;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Sport;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['sportsbook.odds_api.lookahead_days' => 7, 'sportsbook.odds_api.markets' => 'h2h']);
});

test('buys odds only for synced competitions with a fixture inside the lookahead window', function () {
    $due = Competition::factory()->synced()->has(Fixture::factory()->state(['kicks_off_at' => now()->addDays(2)]))->create();
    Competition::factory()->synced()->has(Fixture::factory()->state(['kicks_off_at' => now()->addDays(10)]))->create();
    Competition::factory()->has(Fixture::factory()->state(['kicks_off_at' => now()->addDays(2)]))->create();
    Competition::factory()->synced()->has(Fixture::factory()->kickedOff())->create();
    Queue::fake([SyncCompetitionOdds::class]);

    $this->artisan('sportsbook:sync-odds')->assertSuccessful();

    Queue::assertPushed(SyncCompetitionOdds::class, 1);
    Queue::assertPushed(SyncCompetitionOdds::class, fn (SyncCompetitionOdds $job): bool => $job->competition->is($due)
        && $job->marketTypes === [MarketType::MatchResult, MarketType::Totals, MarketType::Spreads]);
});

test('rejects an unknown market key without queueing anything', function () {
    Competition::factory()->synced()->has(Fixture::factory())->create();
    Queue::fake([SyncCompetitionOdds::class]);

    $this->artisan('sportsbook:sync-odds', ['--markets' => 'h2h,corners'])
        ->expectsOutputToContain('Unknown market key')
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('each sport buys its own markets: football the 1X2, basketball the Winner and totals', function () {
    config(['sportsbook.sports.football.markets' => 'h2h', 'sportsbook.sports.basketball.markets' => 'h2h,totals']);
    $football = Competition::factory()->synced()->for(Sport::factory()->configured('football'))
        ->has(Fixture::factory()->state(['kicks_off_at' => now()->addDays(2)]))->create();
    $basketball = Competition::factory()->synced()->for(Sport::factory()->configured('basketball'))
        ->has(Fixture::factory()->state(['kicks_off_at' => now()->addDays(2)]))->create();
    Queue::fake([SyncCompetitionOdds::class]);

    $this->artisan('sportsbook:sync-odds')->assertSuccessful();

    Queue::assertPushed(SyncCompetitionOdds::class, fn (SyncCompetitionOdds $job): bool => $job->competition->is($football)
        && $job->marketTypes === [MarketType::MatchResult]);
    Queue::assertPushed(SyncCompetitionOdds::class, fn (SyncCompetitionOdds $job): bool => $job->competition->is($basketball)
        && $job->marketTypes === [MarketType::MatchResult, MarketType::Totals]);
});
