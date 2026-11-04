<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\OddsApi\OddsApi;

/**
 * Pulls a competition's upcoming and in-play fixtures. Free, so it can run often to catch kick-off changes.
 */
class SyncFixtures
{
    public function __construct(
        private readonly OddsApi $oddsApi,
        private readonly UpsertFixture $upsertFixture,
    ) {}

    /**
     * @return int The number of fixtures in the feed.
     */
    public function handle(Competition $competition): int
    {
        $events = $this->oddsApi->events($competition->odds_api_key);

        foreach ($events as $event) {
            $this->upsertFixture->handle($competition, $event);
        }

        return count($events);
    }
}
