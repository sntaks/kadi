<?php

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Sync\SyncCompetitions;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    Http::fake(['odds-api.test/v4/sports*' => Http::response(oddsApiFixture('sports'))]);
});

test('imports football competitions but not outright markets or other sports', function () {
    app(SyncCompetitions::class)->handle();

    expect(Competition::orderBy('odds_api_key')->pluck('odds_api_key')->all())->toBe(['soccer_epl', 'soccer_spain_la_liga'])
        ->and(Competition::firstWhere('odds_api_key', 'soccer_epl')->sport->name)->toBe('Football');
});

test('adds new competitions with syncing off and never changes an existing sync switch', function () {
    $synced = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl', 'name' => 'Placeholder']);

    app(SyncCompetitions::class)->handle();

    expect($synced->fresh()->is_synced)->toBeTrue()
        ->and($synced->fresh()->name)->toBe('EPL')
        ->and(Competition::firstWhere('odds_api_key', 'soccer_spain_la_liga')->is_synced)->toBeFalse();
});

test('marks competitions that left the feed as out of season', function () {
    $finished = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_fifa_club_world_cup']);

    app(SyncCompetitions::class)->handle();

    expect($finished->fresh()->is_active_in_feed)->toBeFalse();
});
