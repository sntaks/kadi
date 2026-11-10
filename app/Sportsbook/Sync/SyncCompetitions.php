<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Support\Str;

/**
 * Mirrors the feed's competition list for the configured sports. Free. New competitions arrive with syncing off,
 * and an existing competition's sync switch is never changed here.
 */
class SyncCompetitions
{
    public function __construct(private readonly OddsApi $oddsApi) {}

    /**
     * @return int The number of competitions in the feed for the configured sports.
     */
    public function handle(): int
    {
        /** @var array<string, string> $sportGroups */
        $sportGroups = config('sportsbook.odds_api.sport_groups');

        $inFeed = collect($this->oddsApi->sports())
            ->filter(fn (array $sport): bool => isset($sportGroups[$sport['group']]) && ! $sport['has_outrights']);

        foreach ($inFeed as $feedSport) {
            $sportName = $sportGroups[$feedSport['group']];
            $slug = Str::slug($sportName);
            $sport = Sport::firstOrCreate(['slug' => $slug], ['name' => $sportName, 'sort_order' => SportProfile::find($slug)->sortOrder ?? 99]);

            Competition::updateOrCreate(['odds_api_key' => $feedSport['key']], [
                'sport_id' => $sport->id,
                'name' => $feedSport['title'],
                'description' => $feedSport['description'],
                'is_active_in_feed' => $feedSport['active'],
            ]);
        }

        Competition::query()
            ->whereNotIn('odds_api_key', $inFeed->pluck('key'))
            ->update(['is_active_in_feed' => false]);

        return $inFeed->count();
    }
}
