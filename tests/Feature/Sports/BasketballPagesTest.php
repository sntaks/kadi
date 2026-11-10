<?php

use App\Livewire\Sports\FixtureList;
use App\Livewire\Sports\Search;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;

/** An NBA match with a two-way Winner and a 221.5 points line. */
function basketballFixture(array $attributes = []): Fixture
{
    $sport = Sport::firstWhere('slug', 'basketball') ?? Sport::factory()->configured('basketball')->create();
    $competition = Competition::firstWhere('odds_api_key', 'basketball_nba')
        ?? Competition::factory()->synced()->for($sport)->create(['odds_api_key' => 'basketball_nba', 'name' => 'NBA']);
    $fixture = Fixture::factory()->for($competition)->create(['home_team' => 'New York Knicks', 'away_team' => 'Milwaukee Bucks', 'kicks_off_at' => now('UTC')->addHours(5), ...$attributes]);

    $winner = Market::factory()->for($fixture)->create();
    Selection::factory()->for($winner)->create(['outcome' => 'home', 'name' => 'New York Knicks', 'feed_price' => '1.45']);
    Selection::factory()->for($winner)->create(['outcome' => 'away', 'name' => 'Milwaukee Bucks', 'feed_price' => '2.80']);

    $totals = Market::factory()->for($fixture)->create(['type' => MarketType::Totals, 'line' => '221.50', 'key' => Market::keyFor(MarketType::Totals, 221.5), 'bookmaker_count' => 5]);
    Selection::factory()->for($totals)->create(['outcome' => 'over', 'name' => 'Over 221.5', 'feed_price' => '1.90']);
    Selection::factory()->for($totals)->create(['outcome' => 'under', 'name' => 'Under 221.5', 'feed_price' => '1.90']);

    return $fixture;
}

test('the side menu lists Football and Basketball with their real counts', function () {
    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    basketballFixture();
    basketballFixture(['home_team' => 'Boston Celtics']);

    $this->get('/sports')->assertOk()->assertSeeInOrder(['Football', '1', 'Basketball', '2', 'In play'], false);
});

test('/sports/basketball lists basketball only, with a two-way Winner and the sport sidebar', function () {
    $football = pricedFixture(['home_team' => 'Arsenal', 'kicks_off_at' => now('UTC')->addHours(3)]);
    $match = basketballFixture();

    $this->get('/sports/basketball')
        ->assertOk()
        ->assertSee('<title>Basketball betting · Kadi</title>', false)
        ->assertSee('New York Knicks')
        ->assertDontSee('Arsenal')
        ->assertSee('Winner')
        ->assertSee('Total points')
        ->assertSeeInOrder(['aria-label="Sports and leagues"', 'Highlights', 'Today', 'Popular', 'NBA'], false)
        ->assertSee('href="'.route('sports.competition', $match->competition->slug).'"', false)
        ->assertDontSee('Draw');

    // Football keeps /sports and never shows basketball.
    $this->get('/sports')->assertSee('Arsenal')->assertDontSee('New York Knicks');
    expect($football->id)->toBeInt();
});

test('the basketball list switches to the main points line and shows it', function () {
    basketballFixture();

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'basketball'])
        ->assertSee('1.45')->assertSee('2.80')
        ->set('marketKey', 'totals')
        ->assertSee('221.5')->assertSee('1.90')
        // A football-only market key falls back to the Winner.
        ->set('marketKey', 'btts')
        ->assertSet('marketKey', 'h2h');
});

test('a basketball competition page shows its sport sidebar, and the big match stays football', function () {
    $match = basketballFixture();

    $this->get(route('sports.competition', $match->competition->slug))
        ->assertOk()
        ->assertSee('Basketball')
        ->assertSee('New York Knicks');

    expect(app(SportsLobby::class)->bigMatch())->toBeNull();
});

test('/sports/football redirects to /sports, and every sport slug is reserved from competitions', function () {
    $this->get('/sports/football')->assertRedirect('/sports');

    foreach (array_keys(config('sportsbook.sports')) as $slug) {
        expect(Competition::RESERVED_SLUGS)->toContain($slug);
    }
});

test('search finds basketball and shows its Winner under 1 and 2, never under X', function () {
    basketballFixture();

    $html = Livewire\Livewire::withQueryParams(['q' => 'Knicks'])->test(Search::class)->html();

    expect($html)->toContain('New York Knicks')
        ->and(preg_match('/1\.45.*nt-odds-none.*2\.80/s', $html))->toBe(1);
});
