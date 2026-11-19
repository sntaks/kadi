<?php

use App\Livewire\Sports\FixtureList;
use App\Sportsbook\Betting\Settlement\MarketRules;
use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\SportProfile;

/*
 * Plan 20261120-new-sports-4b-4e (groundwork): a two-way sport can have competitions whose match result has a draw
 * (Test cricket, rugby union), the big match stays football, and a sport can be settled by hand until its feed
 * results are checked.
 */

beforeEach(function () {
    // A made-up two-way sport with one drawn competition, so this test never depends on which real sports exist.
    config(['sportsbook.sports.testball' => [
        'name' => 'Testball',
        'icon' => 'ball',
        'sort_order' => 50,
        'key_prefix' => 'testball_',
        'has_draw' => false,
        'derived_markets' => false,
        'markets' => 'h2h',
        'max_score' => 1,
        'draw_competitions' => ['testball_long', 'testball_union_*'],
        'auto_settle' => false,
    ]]);
});

function testballFixture(string $key, bool $withDraw): Fixture
{
    $sport = Sport::firstWhere('slug', 'testball') ?? Sport::factory()->configured('testball')->create();
    $competition = Competition::firstWhere('odds_api_key', $key)
        ?? Competition::factory()->synced()->for($sport)->create(['odds_api_key' => $key, 'name' => Str::headline($key)]);
    $fixture = Fixture::factory()->for($competition)->create(['home_team' => "Home {$key}", 'away_team' => "Away {$key}", 'kicks_off_at' => now('UTC')->addHours(5)]);

    $market = Market::factory()->for($fixture)->create();
    Selection::factory()->for($market)->create(['outcome' => 'home', 'name' => "Home {$key}", 'feed_price' => '2.10']);
    if ($withDraw) {
        Selection::factory()->for($market)->create(['outcome' => 'draw', 'name' => 'Draw', 'feed_price' => '4.40']);
    }
    Selection::factory()->for($market)->create(['outcome' => 'away', 'name' => "Away {$key}", 'feed_price' => '1.70']);

    return $fixture;
}

test('a competition listed in draw_competitions has a draw; the rest of its sport stays two-way', function () {
    $long = testballFixture('testball_long', true)->competition;
    $short = testballFixture('testball_short', false)->competition;
    $union = testballFixture('testball_union_six', true)->competition;

    expect(SportProfile::find('testball')->hasDraw)->toBeFalse()
        ->and(SportProfile::find('testball')->hasDrawAnywhere())->toBeTrue()
        ->and(SportProfile::forCompetition($long)->hasDraw)->toBeTrue()
        ->and(SportProfile::forCompetition($union)->hasDraw)->toBeTrue()
        ->and(SportProfile::forCompetition($short)->hasDraw)->toBeFalse()
        ->and(SportProfile::forCompetition($long)->matchResultOutcomes())->toBe(['home', 'draw', 'away'])
        ->and(SportProfile::forCompetition(null)->slug)->toBe('football')
        // Sports without the option are unchanged.
        ->and(SportProfile::find('basketball')->hasDrawAnywhere())->toBeFalse()
        ->and(SportProfile::find('football')->hasDrawAnywhere())->toBeTrue();
});

test('a drawn competition settles a draw as a result; a two-way one voids its level score', function () {
    $draw = new FixtureResult(['home_score' => 0, 'away_score' => 0, 'is_void' => false]);

    $leg = fn (Fixture $fixture, string $outcome) => tap(new BetLeg(['market_type' => MarketType::MatchResult, 'outcome' => $outcome]), fn (BetLeg $leg) => $leg->fixture_id = $fixture->id);
    $long = testballFixture('testball_long', true);
    $short = testballFixture('testball_short', false);

    expect(MarketRules::settle($leg($long, 'draw'), $draw))->toBe(BetLegStatus::Won)
        ->and(MarketRules::settle($leg($long, 'home'), $draw))->toBe(BetLegStatus::Lost)
        ->and(MarketRules::settle($leg($short, 'home'), $draw))->toBe(BetLegStatus::Void);
});

test('the market is named 1X2 in a drawn competition and Winner in a two-way one', function () {
    $long = testballFixture('testball_long', true)->markets()->first();
    $short = testballFixture('testball_short', false)->markets()->first();

    expect($long->fresh()->hasDraw())->toBeTrue()
        ->and($short->fresh()->hasDraw())->toBeFalse()
        ->and($short->fresh()->name())->toBe('Winner');
});

test('the sport list shows 1 X 2 with an empty X for two-way rows; a two-way competition page shows 1 2', function () {
    $long = testballFixture('testball_long', true);
    $short = testballFixture('testball_short', false);

    expect(FixtureListing::isTwoWay('testball'))->toBeFalse()
        ->and(FixtureListing::isTwoWayCompetition($short->competition))->toBeTrue()
        ->and(FixtureListing::listMarkets('testball'))->toBe(['h2h' => '1X2']);

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'testball'])
        ->assertViewHas('columns', 3)
        ->assertSee('4.40')
        ->assertSee('Home testball_short');

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'testball', 'competitionId' => $short->competition_id])
        ->assertViewHas('columns', 2)
        ->assertViewHas('labels', ['1', '2']);

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'testball', 'competitionId' => $long->competition_id])
        ->assertViewHas('columns', 3);
});

test('the big match is football only, never another sport with a draw', function () {
    testballFixture('testball_long', true);

    expect(app(SportsLobby::class)->bigMatch())->toBeNull();

    $football = pricedFixture(['home_team' => 'Arsenal', 'kicks_off_at' => now('UTC')->addHours(3)]);

    expect(app(SportsLobby::class)->bigMatch()?->is($football))->toBeTrue();
});

test('a sport set to settle by hand creates its competitions with auto_settle off; others stay automatic', function () {
    $sport = Sport::factory()->configured('testball')->create();

    expect(Competition::factory()->for($sport)->create(['odds_api_key' => 'testball_new'])->auto_settle)->toBeFalse()
        ->and(Competition::factory()->create(['odds_api_key' => 'basketball_nba'])->auto_settle)->toBeTrue()
        ->and(Competition::factory()->create(['odds_api_key' => 'soccer_uefa_champs_league'])->auto_settle)->toBeFalse();
});

test('a sport page with nothing on offer says so instead of an empty list', function () {
    // Basketball has a page (its route exists from config); with no competitions open it shows the message.
    $this->get('/sports/basketball')
        ->assertOk()
        ->assertSee('No Basketball on offer right now')
        ->assertSee('Events show here about a week before they start.');

    testballFixture('testball_short', false);
    // Football keeps its list and the per-day message, never the out-of-season one.
    $this->get('/sports')->assertOk()->assertDontSee('on offer right now');
});
