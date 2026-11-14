<?php

namespace Database\Seeders\Sportsbook;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Database\Seeder;

/**
 * Switches syncing on for the default competitions, in config order. Names are placeholders until the next fixture
 * sync replaces them with the feed's. For a fresh install: it resets every default competition's order and switches
 * them all back on, so on a live site add competitions with `sportsbook:enable-competitions` instead.
 */
class CompetitionSeeder extends Seeder
{
    /**
     * Placeholder names (the feed's titles), which also give a new competition its URL slug.
     *
     * @var array<string, string>
     */
    public const NAMES = [
        'soccer_epl' => 'EPL',
        'soccer_spain_la_liga' => 'La Liga - Spain',
        'soccer_italy_serie_a' => 'Serie A - Italy',
        'soccer_germany_bundesliga' => 'Bundesliga - Germany',
        'soccer_france_ligue_one' => 'Ligue 1 - France',
        'soccer_uefa_champs_league' => 'UEFA Champions League',
        'basketball_nba' => 'NBA',
        'basketball_euroleague' => 'Basketball Euroleague',
        'soccer_uefa_europa_league' => 'UEFA Europa League',
        'soccer_uefa_europa_conference_league' => 'UEFA Europa Conference League',
        'soccer_uefa_nations_league' => 'UEFA Nations League',
        'soccer_efl_champ' => 'Championship',
        'soccer_netherlands_eredivisie' => 'Dutch Eredivisie',
        'soccer_portugal_primeira_liga' => 'Primeira Liga - Portugal',
        'soccer_spl' => 'Premiership - Scotland',
        'soccer_turkey_super_league' => 'Turkey Super League',
        'soccer_brazil_campeonato' => 'Brazil Série A',
        'soccer_usa_mls' => 'MLS',
        'soccer_england_efl_cup' => 'EFL Cup',
        'soccer_fa_cup' => 'FA Cup',
        'soccer_germany_dfb_pokal' => 'DFB-Pokal',
        'soccer_spain_copa_del_rey' => 'Copa del Rey',
        'soccer_fifa_world_cup' => 'FIFA World Cup',
        'soccer_fifa_world_cup_qualifiers_europe' => 'FIFA World Cup Qualifiers - Europe',
        'soccer_fifa_world_cup_qualifiers_south_america' => 'FIFA World Cup Qualifiers - South America',
        'soccer_africa_cup_of_nations' => 'Africa Cup of Nations',
        'soccer_uefa_european_championship' => 'UEFA Euro',
        'soccer_uefa_euro_qualification' => 'UEFA Euro Qualification',
        'basketball_nbl' => 'NBL',
        'icehockey_nhl' => 'NHL',
        'icehockey_sweden_hockey_league' => 'SHL',
        'icehockey_liiga' => 'Liiga',
        'icehockey_ahl' => 'AHL',
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
