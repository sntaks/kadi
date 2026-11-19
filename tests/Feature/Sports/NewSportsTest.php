<?php

use App\Livewire\Sports\FixtureList;
use App\Sportsbook\Betting\Settlement\MarketRules;
use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\CompetitionRegion;
use App\Sportsbook\Support\SportProfile;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/*
 * Plan 20261120 (phases 4b-4e): tennis, cricket, rugby, boxing and MMA. Prices come from real recordings
 * (2026-11-19); none of the sports had a finished result to check, so all start settled by hand.
 */

function newSportCompetition(string $slug, string $key, string $name): Competition
{
    $sport = Sport::firstWhere('slug', $slug) ?? Sport::factory()->configured($slug)->create(['name' => SportProfile::find($slug)->name]);

    return Competition::factory()->synced()->for($sport)->create(['odds_api_key' => $key, 'name' => $name, 'auto_settle' => null]);
}

/** Prices one recorded competition and returns the match result market of its first event. */
function syncedMatchResult(Competition $competition, string $eventId): Market
{
    Http::fake(["odds-api.test/v4/sports/{$competition->odds_api_key}/odds*" => Http::response(oddsApiFixture("odds-{$competition->odds_api_key}"), 200, ['x-requests-last' => '1', 'x-requests-remaining' => '99000'])]);

    app(SyncOdds::class)->handle($competition, [MarketType::MatchResult]);

    return Fixture::firstWhere('external_id', $eventId)->markets()->where('key', 'h2h')->with('selections')->sole();
}

function settleOn(Competition $competition, string $outcome, int $home, int $away): ?BetLegStatus
{
    $fixture = Fixture::factory()->for($competition)->create();
    $leg = new BetLeg(['market_type' => MarketType::MatchResult, 'outcome' => $outcome]);
    $leg->fixture_id = $fixture->id;

    return MarketRules::settle($leg, new FixtureResult(['home_score' => $home, 'away_score' => $away, 'is_void' => false]));
}

beforeEach(function () {
    useLiveOddsApi();
    config(['sportsbook.odds.margin' => 0.06, 'sportsbook.odds.minimum_bookmakers' => 3]);
    $this->travelTo(Date::parse('2026-11-19 20:00:00', 'UTC'));
});

test('each new sport is configured with its slug, prefix, icon, draw rule and manual settlement', function (string $slug, string $key, bool $draw, array $markets, int $maxScore) {
    $profile = SportProfile::forCompetitionKey($key);

    expect($profile?->slug)->toBe($slug)
        ->and(SportProfile::forCompetition(newSportCompetition($slug, $key, $key))->hasDraw)->toBe($draw)
        ->and($profile->derivedMarkets)->toBeFalse()
        ->and($profile->markets)->toBe($markets)
        ->and($profile->maxScore)->toBe($maxScore)
        ->and($profile->autoSettle)->toBeFalse()
        ->and(Competition::RESERVED_SLUGS)->toContain($slug)
        ->and(config('sportsbook.odds_api.default_competitions'))->toContain($key);

    $this->blade('<x-site.icon :name="$name" />', ['name' => $profile->icon])->assertSee('<path', false);
})->with([
    'tennis' => ['tennis', 'tennis_atp_aus_open_singles', false, [MarketType::MatchResult], 3],
    'cricket Test (draw)' => ['cricket', 'cricket_test_match', true, [MarketType::MatchResult], 1],
    'cricket T20 (two-way)' => ['cricket', 'cricket_international_t20', false, [MarketType::MatchResult], 1],
    'rugby league (two-way)' => ['rugby', 'rugbyleague_nrl', false, [MarketType::MatchResult, MarketType::Totals], 150],
    'rugby union (draw)' => ['rugby', 'rugbyunion_six_nations', true, [MarketType::MatchResult, MarketType::Totals], 150],
    'boxing (draw)' => ['boxing', 'boxing_boxing', true, [MarketType::MatchResult], 1],
    'MMA (two-way)' => ['mma', 'mma_mixed_martial_arts', false, [MarketType::MatchResult], 1],
]);

test('feed groups map to the new sports and their competitions start settled by hand', function () {
    expect(config('sportsbook.odds_api.sport_groups'))->toMatchArray([
        'Tennis' => 'Tennis', 'Cricket' => 'Cricket', 'Rugby League' => 'Rugby', 'Rugby Union' => 'Rugby',
        'Boxing' => 'Boxing', 'Mixed Martial Arts' => 'MMA',
    ])
        ->and(newSportCompetition('mma', 'mma_mixed_martial_arts', 'MMA')->auto_settle)->toBeFalse()
        ->and(newSportCompetition('cricket', 'cricket_odi', 'One Day Internationals')->auto_settle)->toBeFalse()
        ->and(CompetitionRegion::code('cricket_big_bash'))->toBe('au')
        ->and(CompetitionRegion::code('rugbyleague_nrl'))->toBe('au')
        ->and(CompetitionRegion::code('cricket_ipl'))->toBe('in')
        ->and(CompetitionRegion::code('tennis_atp_wimbledon'))->toBe('world');
});

test('a boxing fight is priced 1X2 from the books that price the draw', function () {
    $market = syncedMatchResult(newSportCompetition('boxing', 'boxing_boxing', 'Boxing'), '888e7a7347f634d39b6e47b831e096bd');

    expect($market->status)->toBe(MarketStatus::Open)
        ->and($market->selections->pluck('outcome')->sort()->values()->all())->toBe(['away', 'draw', 'home'])
        ->and($market->selections->firstWhere('outcome', 'draw')->name)->toBe('Draw')
        // Five of the six books price the draw; the two-way one (unibet) drops out.
        ->and($market->bookmaker_count)->toBe(5)
        ->and($market->name())->toBe('1X2');
});

test('an MMA fight and a T20 are two-way Winners; a Test is 1X2', function () {
    $mma = syncedMatchResult(newSportCompetition('mma', 'mma_mixed_martial_arts', 'MMA'), '3252a6edf971e56a6d153f473f6b6edd');
    $t20 = syncedMatchResult(newSportCompetition('cricket', 'cricket_international_t20', 'International Twenty20'), '88effd7d2c1313bf6c1de12f924f149d');
    $test = syncedMatchResult(newSportCompetition('cricket', 'cricket_test_match', 'Test Matches'), 'd68619f9f0d18808c5629bc369dbe651');

    expect($mma->selections->pluck('outcome')->sort()->values()->all())->toBe(['away', 'home'])
        ->and($mma->name())->toBe('Winner')
        ->and($t20->selections->pluck('outcome')->sort()->values()->all())->toBe(['away', 'home'])
        ->and($test->selections->pluck('outcome')->sort()->values()->all())->toBe(['away', 'draw', 'home'])
        ->and($test->status)->toBe(MarketStatus::Open);
});

test('results recorded as scores settle by each sport\'s rules', function () {
    $boxing = newSportCompetition('boxing', 'boxing_boxing', 'Boxing');
    $mma = newSportCompetition('mma', 'mma_mixed_martial_arts', 'MMA');
    $test = newSportCompetition('cricket', 'cricket_test_match', 'Test Matches');
    $t20 = newSportCompetition('cricket', 'cricket_international_t20', 'International Twenty20');
    $tennis = newSportCompetition('tennis', 'tennis_atp_aus_open_singles', 'ATP Australian Open');

    expect(settleOn($boxing, 'draw', 0, 0))->toBe(BetLegStatus::Won)            // boxing draw wins Draw
        ->and(settleOn($boxing, 'home', 0, 0))->toBe(BetLegStatus::Lost)        // and loses the fighters
        ->and(settleOn($boxing, 'away', 0, 1))->toBe(BetLegStatus::Won)
        ->and(settleOn($mma, 'home', 0, 0))->toBe(BetLegStatus::Void)           // MMA draw voids
        ->and(settleOn($mma, 'home', 1, 0))->toBe(BetLegStatus::Won)
        ->and(settleOn($test, 'draw', 0, 0))->toBe(BetLegStatus::Won)           // Test draw is a result
        ->and(settleOn($t20, 'away', 0, 0))->toBe(BetLegStatus::Void)           // limited-overs tie voids
        ->and(settleOn($tennis, 'home', 2, 1))->toBe(BetLegStatus::Won)         // sets won
        ->and(settleOn($tennis, 'away', 2, 1))->toBe(BetLegStatus::Lost);
});

test('/sports/mma lists fights with a Winner column and the MMA leagues', function () {
    $competition = newSportCompetition('mma', 'mma_mixed_martial_arts', 'MMA');
    syncedMatchResult($competition, '3252a6edf971e56a6d153f473f6b6edd');

    $this->get('/sports/mma')
        ->assertOk()
        ->assertSee('<title>MMA betting · Kadi</title>', false)
        ->assertSee('Alik Lorenz')
        ->assertSee('Winner')
        ->assertDontSee('Draw');

    $this->get('/sports')->assertSeeInOrder(['Football', 'MMA', 'In play'], false);
});

test('the cricket list shows 1 X 2 for a Test and leaves X empty on a T20', function () {
    syncedMatchResult(newSportCompetition('cricket', 'cricket_international_t20', 'International Twenty20'), '88effd7d2c1313bf6c1de12f924f149d');
    syncedMatchResult(newSportCompetition('cricket', 'cricket_test_match', 'Test Matches'), 'd68619f9f0d18808c5629bc369dbe651');

    expect(FixtureListing::listMarkets('cricket'))->toBe(['h2h' => '1X2']);

    Livewire\Livewire::test(FixtureList::class, ['sport' => 'cricket', 'tab' => 'upcoming'])
        ->assertViewHas('columns', 3)
        ->assertSee('New Zealand')
        ->assertSee('Australia');
});

test('tennis and rugby, out of season, have a page that says so and no menu entry', function () {
    $this->get('/sports/tennis')->assertOk()->assertSee('No Tennis on offer right now');
    $this->get('/sports/rugby')->assertOk()->assertSee('No Rugby on offer right now');

    $this->get('/sports')->assertDontSee('href="'.route('sports.sport', 'tennis').'"', false);
});
