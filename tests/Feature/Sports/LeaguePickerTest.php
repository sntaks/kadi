<?php

use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Support\CompetitionRegion;

/*
 * The sport page's league picker: leagues grouped by country or region with round flags and real counts
 * (config sportsbook.regions, App\Sportsbook\Support\CompetitionRegion).
 */

beforeEach(function () {
    config(['platform.sports' => true]);
});

/** A priced fixture in a competition with this feed key and name. */
function leagueFixture(string $key, string $name, array $attributes = []): Fixture
{
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3), ...$attributes]);
    $fixture->competition->update(['odds_api_key' => $key, 'name' => $name]);

    return $fixture;
}

test('competition keys map to countries and regions', function (string $key, string $code) {
    expect(CompetitionRegion::code($key))->toBe($code);
})->with([
    ['soccer_spain_la_liga', 'es'],
    ['soccer_epl', 'gb-eng'],
    ['soccer_england_league1', 'gb-eng'],
    ['soccer_spl', 'gb-sct'],
    ['soccer_saudi_arabia_pro_league', 'sa'],
    ['soccer_korea_kleague1', 'kr'],
    ['soccer_usa_mls', 'us'],
    ['soccer_uefa_champs_league', 'eu'],
    ['soccer_conmebol_copa_libertadores', 'south-america'],
    ['soccer_africa_cup_of_nations', 'africa'],
    ['soccer_fifa_world_cup', 'world'],
    ['basketball_nba', 'us'],
    ['icehockey_sweden_hockey_league', 'se'],
    ['soccer_someplace_new_league', 'world'],
]);

test('every configured competition has a region, and every country a flag file', function () {
    $keys = [
        ...(array) config('sportsbook.odds_api.default_competitions'),
        ...collect(config('sportsbook.sports'))->pluck('popular')->flatten()->all(),
    ];

    foreach ($keys as $key) {
        $code = CompetitionRegion::code($key);
        expect(CompetitionRegion::name($code))->not->toBe('', $key);

        // Only the four regions may show a globe; a country without a flag file is a mistake.
        if (! CompetitionRegion::isRegion($code) || $code === 'eu') {
            expect(CompetitionRegion::flag($code))->not->toBeNull("{$key} → {$code} has no flag");
        }
    }

    foreach (array_keys((array) config('sportsbook.regions.countries')) as $code) {
        expect(public_path("images/flags/{$code}.svg"))->toBeFile();
    }
});

test('flags never resolve outside the flags folder', function () {
    expect(CompetitionRegion::flag('../../index'))->toBeNull()
        ->and(CompetitionRegion::flag('world'))->toBeNull();
});

test('groups put regions first, then countries A-Z, with real totals', function () {
    $row = fn (string $slug, string $region, int $count) => ['name' => $slug, 'slug' => $slug, 'count' => $count, 'region' => $region];

    $groups = CompetitionRegion::group([
        $row('la-liga', 'es', 5), $row('ucl', 'eu', 3), $row('epl', 'gb-eng', 7), $row('copa', 'es', 2), $row('wc', 'world', 1),
    ]);

    expect(array_column($groups, 'code'))->toBe(['world', 'eu', 'gb-eng', 'es'])
        ->and(array_column($groups, 'count'))->toBe([1, 3, 7, 7])
        ->and(array_column($groups[3]['competitions'], 'slug'))->toBe(['la-liga', 'copa']);
});

test('the sport page lists leagues by country with flags and real counts', function () {
    $liga = leagueFixture('soccer_spain_la_liga', 'La Liga');
    $copa = leagueFixture('soccer_spain_copa_del_rey', 'Copa del Rey');
    leagueFixture('soccer_epl', 'Premier League');

    $this->get('/sports')
        ->assertOk()
        ->assertSee('aria-label="Search leagues or countries"', false)
        ->assertSeeInOrder(['Countries', 'England', 'Premier League', 'Spain', 'La Liga', 'Copa del Rey'])
        ->assertSee('images/flags/es.svg', false)
        ->assertSee('data-search="premier league england premier league epl"', false)
        ->assertSee('All Football leagues');

    // On a competition page its country starts open and the button names it.
    $page = $this->get(route('sports.competition', $copa->competition->slug))
        ->assertOk()
        ->assertSeeInOrder(['nt-lp-trigger', 'Copa del Rey', 'Spain']);

    expect(preg_match_all('/<details class="nt-lp-country"\s+open\s*>/', $page->getContent()))->toBeGreaterThan(0)
        ->and(preg_match('/<details class="nt-lp-country"\s*>\s*<summary[^>]*>.*?<span>England<\/span>/s', $page->getContent()))->toBe(0);

    expect($liga->competition->slug)->not->toBe($copa->competition->slug);
});

test('a country with one league links straight to it', function () {
    $fixture = leagueFixture('soccer_netherlands_eredivisie', 'Eredivisie');

    $html = $this->get('/sports')->assertOk()->getContent();

    expect($html)->toContain('<b>Netherlands</b><em>Eredivisie</em>')
        ->and(substr_count($html, 'href="'.route('sports.competition', $fixture->competition->slug).'"'))->toBeGreaterThan(0)
        ->and($html)->not->toContain('<span>Netherlands</span>');
});
