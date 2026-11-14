<?php

use App\Livewire\Sports\FixtureList;
use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\SportProfile;

/*
 * Plan §10.5 phase 4a: ice hockey, a two-way sport like basketball (Winner incl. overtime and the shootout, totals).
 */

/** An NHL game with a two-way Winner and a 6.5 goals line. */
function hockeyFixture(array $attributes = []): Fixture
{
    $sport = Sport::firstWhere('slug', 'ice-hockey') ?? Sport::factory()->configured('ice-hockey')->create();
    $competition = Competition::firstWhere('odds_api_key', 'icehockey_nhl')
        ?? Competition::factory()->synced()->for($sport)->create(['odds_api_key' => 'icehockey_nhl', 'name' => 'NHL']);
    $fixture = Fixture::factory()->for($competition)->create(['home_team' => 'Toronto Maple Leafs', 'away_team' => 'Edmonton Oilers', 'kicks_off_at' => now('UTC')->addHours(5), ...$attributes]);

    $winner = Market::factory()->for($fixture)->create();
    Selection::factory()->for($winner)->create(['outcome' => 'home', 'name' => 'Toronto Maple Leafs', 'feed_price' => '2.21']);
    Selection::factory()->for($winner)->create(['outcome' => 'away', 'name' => 'Edmonton Oilers', 'feed_price' => '1.64']);

    $totals = Market::factory()->for($fixture)->create(['type' => MarketType::Totals, 'line' => '6.50', 'key' => Market::keyFor(MarketType::Totals, 6.5), 'bookmaker_count' => 5]);
    Selection::factory()->for($totals)->create(['outcome' => 'over', 'name' => 'Over 6.5', 'feed_price' => '1.95']);
    Selection::factory()->for($totals)->create(['outcome' => 'under', 'name' => 'Under 6.5', 'feed_price' => '1.85']);

    return $fixture;
}

test('ice hockey is configured as a two-way sport with its own slug, icon and totals name', function () {
    $profile = SportProfile::find('ice-hockey');

    expect($profile)->not->toBeNull()
        ->and($profile->hasDraw)->toBeFalse()
        ->and($profile->derivedMarkets)->toBeFalse()
        ->and($profile->markets)->toBe([MarketType::MatchResult, MarketType::Totals])
        ->and($profile->totalsLabel)->toBe('Total goals')
        ->and(SportProfile::forCompetitionKey('icehockey_sweden_hockey_league')?->slug)->toBe('ice-hockey')
        ->and(Competition::RESERVED_SLUGS)->toContain('ice-hockey')
        ->and(config('sportsbook.odds_api.sport_groups'))->toHaveKey('Ice Hockey')
        ->and(FixtureListing::listMarkets('ice-hockey'))->toBe(['h2h' => 'Winner', 'totals' => 'Total goals'])
        ->and(FixtureListing::listMarkets('basketball'))->toBe(['h2h' => 'Winner', 'totals' => 'Total points']);

    $this->blade('<x-site.icon name="ice-hockey" />')->assertSee('<ellipse', false);
});

test('/sports/ice-hockey lists hockey only, with a two-way Winner, the goals line and its leagues', function () {
    pricedFixture(['home_team' => 'Arsenal', 'kicks_off_at' => now('UTC')->addHours(3)]);
    $match = hockeyFixture();

    $this->get('/sports/ice-hockey')
        ->assertOk()
        ->assertSee('<title>Ice Hockey betting · Kadi</title>', false)
        ->assertSee('Toronto Maple Leafs')
        ->assertDontSee('Arsenal')
        ->assertSee('Winner')
        ->assertSee('Total goals')
        ->assertSeeInOrder(['aria-label="Sports and leagues"', 'Popular', 'NHL'], false)
        ->assertSee('href="'.route('sports.competition', $match->competition->slug).'"', false)
        ->assertDontSee('Draw');

    $this->get('/sports')->assertSeeInOrder(['Football', 'Ice Hockey', 'In play'], false);
});

test('the hockey list switches to the main goals line', function () {
    hockeyFixture();

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'ice-hockey'])
        ->assertSee('2.21')->assertSee('1.64')
        ->set('marketKey', 'totals')
        ->assertSee('6.5')->assertSee('1.95');
});

test('the big match stays football', function () {
    hockeyFixture();

    expect(app(SportsLobby::class)->bigMatch())->toBeNull();
});
