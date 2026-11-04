<?php

use App\Jobs\Sportsbook\SyncCompetitionFixtures;
use App\Sportsbook\Models\Competition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('refreshes the competition list and queues fixtures for synced competitions only', function () {
    useLiveOddsApi();
    Http::fake(['odds-api.test/v4/sports*' => Http::response(oddsApiFixture('sports'))]);
    $synced = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
    Queue::fake([SyncCompetitionFixtures::class]);

    $this->artisan('sportsbook:sync-fixtures')->assertSuccessful();

    Queue::assertPushed(SyncCompetitionFixtures::class, 1);
    Queue::assertPushed(SyncCompetitionFixtures::class, fn (SyncCompetitionFixtures $job): bool => $job->competition->is($synced));
    expect(Competition::where('odds_api_key', 'soccer_spain_la_liga')->exists())->toBeTrue();
});
