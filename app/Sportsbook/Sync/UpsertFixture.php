<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Creates or updates a fixture from a feed event. Shared by the fixture and odds syncs, which both receive events.
 */
class UpsertFixture
{
    /**
     * The fixtures the feed's events already have, keyed by external id, in one query instead of one per event
     * (Sentry KADI-B). Pass it to handle().
     *
     * @param  list<array{id: string}>  $events
     * @param  list<string>  $with  Relations to eager load.
     * @return Collection<string, Fixture>
     */
    public function existing(array $events, array $with = []): Collection
    {
        if ($events === []) {
            return new Collection;
        }

        return Fixture::query()->with($with)->whereIn('external_id', array_column($events, 'id'))->get()->keyBy('external_id');
    }

    /**
     * @param  array{id: string, commence_time: string, home_team: string, away_team: string}  $event
     * @param  Collection<string, Fixture>|null  $existing  From existing(): saves the lookup, and a new fixture joins it
     *                                                      so the same event twice in one feed is still one fixture.
     */
    public function handle(Competition $competition, array $event, ?Collection $existing = null): Fixture
    {
        $fixture = $existing === null
            ? Fixture::firstOrNew(['external_id' => $event['id']])
            : $existing->get($event['id']) ?? new Fixture(['external_id' => $event['id']]);

        $fixture->fill([
            'competition_id' => $competition->id,
            'home_team' => $event['home_team'],
            'away_team' => $event['away_team'],
            'kicks_off_at' => Carbon::parse($event['commence_time']),
            'last_seen_in_feed_at' => now('UTC'),
        ])->save();

        $existing?->put($event['id'], $fixture);

        return $fixture;
    }
}
