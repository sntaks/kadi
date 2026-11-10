<?php

namespace Database\Seeders\Sportsbook;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Database\Seeder;

/**
 * Switches syncing on for the default competitions. Names are placeholders until the next fixture sync
 * replaces them with the feed's. Safe to run repeatedly.
 */
class CompetitionSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const NAMES = [
        'soccer_epl' => 'EPL',
        'soccer_spain_la_liga' => 'La Liga - Spain',
        'soccer_italy_serie_a' => 'Serie A - Italy',
        'soccer_germany_bundesliga' => 'Bundesliga - Germany',
        'soccer_france_ligue_one' => 'Ligue 1 - France',
        'soccer_uefa_champs_league' => 'UEFA Champions League',
        'basketball_nba' => 'NBA',
        'basketball_euroleague' => 'Basketball Euroleague',
    ];

    public function run(): void
    {
        /** @var list<string> $keys */
        $keys = config('sportsbook.odds_api.default_competitions');

        foreach ($keys as $position => $key) {
            // Each key's sport comes from its prefix (`soccer_`, `basketball_`); anything else is football, as before.
            $profile = SportProfile::forCompetitionKey($key) ?? SportProfile::find('football');
            $sport = Sport::firstOrCreate(['slug' => $profile->slug ?? 'football'], ['name' => $profile->name ?? 'Football', 'sort_order' => $profile->sortOrder ?? 1]);

            Competition::updateOrCreate(['odds_api_key' => $key], [
                'sport_id' => $sport->id,
                'name' => Competition::where('odds_api_key', $key)->value('name') ?? self::NAMES[$key] ?? $key,
                'is_synced' => true,
                'sort_order' => $position + 1,
            ]);
        }
    }
}
