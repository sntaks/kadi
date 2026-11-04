<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use Illuminate\Support\Carbon;

/**
 * Creates or updates a fixture from a feed event. Shared by the fixture and odds syncs, which both receive events.
 */
class UpsertFixture
{
    /**
     * @param  array{id: string, commence_time: string, home_team: string, away_team: string}  $event
     */
    public function handle(Competition $competition, array $event): Fixture
    {
        return Fixture::updateOrCreate(['external_id' => $event['id']], [
            'competition_id' => $competition->id,
            'home_team' => $event['home_team'],
            'away_team' => $event['away_team'],
            'kicks_off_at' => Carbon::parse($event['commence_time']),
            'last_seen_in_feed_at' => now('UTC'),
        ]);
    }
}
