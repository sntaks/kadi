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
        'tennis_atp_aus_open_singles' => 'ATP Australian Open',
        'tennis_wta_aus_open_singles' => 'WTA Australian Open',
        'tennis_atp_french_open' => 'ATP French Open',
        'tennis_wta_french_open' => 'WTA French Open',
        'tennis_atp_wimbledon' => 'ATP Wimbledon',
        'tennis_wta_wimbledon' => 'WTA Wimbledon',
        'tennis_atp_us_open' => 'ATP US Open',
        'tennis_wta_us_open' => 'WTA US Open',
        'tennis_atp_indian_wells' => 'ATP Indian Wells',
        'tennis_wta_indian_wells' => 'WTA Indian Wells',
        'tennis_atp_miami_open' => 'ATP Miami Open',
        'tennis_wta_miami_open' => 'WTA Miami Open',
        'tennis_atp_monte_carlo_masters' => 'ATP Monte-Carlo Masters',
        'tennis_atp_madrid_open' => 'ATP Madrid Open',
        'tennis_wta_madrid_open' => 'WTA Madrid Open',
        'tennis_atp_italian_open' => 'ATP Italian Open',
        'tennis_wta_italian_open' => 'WTA Italian Open',
        'tennis_atp_canadian_open' => 'ATP Canadian Open',
        'tennis_wta_canadian_open' => 'WTA Canadian Open',
        'tennis_atp_cincinnati_open' => 'ATP Cincinnati Open',
        'tennis_wta_cincinnati_open' => 'WTA Cincinnati Open',
        'tennis_atp_shanghai_masters' => 'ATP Shanghai Masters',
        'tennis_atp_paris_masters' => 'ATP Paris Masters',
        'tennis_atp_finals' => 'ATP Finals',
        'tennis_wta_finals' => 'WTA Finals',
        'cricket_test_match' => 'Test Matches',
        'cricket_odi' => 'One Day Internationals',
        'cricket_international_t20' => 'International Twenty20',
        'cricket_big_bash' => 'Big Bash',
        'cricket_ipl' => 'IPL',
        'cricket_t20_world_cup' => 'T20 World Cup',
        'rugbyleague_nrl' => 'NRL',
        'rugbyunion_six_nations' => 'Six Nations',
        'boxing_boxing' => 'Boxing',
        'mma_mixed_martial_arts' => 'MMA',
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
