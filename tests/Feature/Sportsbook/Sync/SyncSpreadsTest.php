<?php

use App\Sportsbook\Catalogue\MarketTabs;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\OddsApiCall;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * Plan §10.5 phase 1: football handicaps (the feed's `spreads`), bought from their own bookmakers, whole and half
 * lines only.
 */

beforeEach(function () {
    useLiveOddsApi();
    config([
        'sportsbook.odds.margin' => 0.06,
        'sportsbook.odds.minimum_bookmakers' => 3,
        'sportsbook.odds_api.market_bookmakers.spreads' => 'pinnacle,onexbet,marathonbet,bovada',
    ]);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
});

/**
 * One EPL match as the feed sends it: the main markets from the UK books, handicaps from the handicap books.
 *
 * @param  array<string, list<array{0: float, 1: float, 2: float}>>  $spreads  book => [[home line, home price, away price], ...]
 * @return list<array<string, mixed>>
 */
function spreadsEvent(array $spreads): array
{
    $bookmakers = [];

    foreach ($spreads as $book => $lines) {
        $bookmakers[] = ['key' => $book, 'markets' => array_map(fn (array $line): array => ['key' => 'spreads', 'outcomes' => [
            ['name' => 'Arsenal', 'price' => $line[1], 'point' => $line[0]],
            ['name' => 'Chelsea', 'price' => $line[2], 'point' => -$line[0]],
        ]], $lines)];
    }

    return [['id' => 'evt-ars-che', 'sport_key' => 'soccer_epl', 'commence_time' => '2026-10-19T15:00:00Z', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'bookmakers' => $bookmakers]];
}

function mainEvent(): array
{
    $h2h = fn (float $home, float $draw, float $away): array => ['key' => 'h2h', 'outcomes' => [
        ['name' => 'Arsenal', 'price' => $home], ['name' => 'Draw', 'price' => $draw], ['name' => 'Chelsea', 'price' => $away],
    ]];

    return [['id' => 'evt-ars-che', 'sport_key' => 'soccer_epl', 'commence_time' => '2026-10-19T15:00:00Z', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'bookmakers' => [
        ['key' => 'williamhill', 'markets' => [$h2h(1.9, 3.6, 4.0)]],
        ['key' => 'betfair_ex_uk', 'markets' => [$h2h(1.95, 3.7, 4.1)]],
        ['key' => 'skybet', 'markets' => [$h2h(1.92, 3.6, 4.0)]],
    ]]];
}

/** Answers the main-markets call and the handicap call separately, as the API would. */
function fakeOddsFeed(array $main, array|int $spreads): void
{
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => fn (Request $request) => isset($request['bookmakers'])
        ? (is_int($spreads) ? Http::response([], $spreads) : Http::response($spreads, 200, ['x-requests-last' => '1', 'x-requests-remaining' => '98000']))
        : Http::response($main, 200, ['x-requests-last' => '1', 'x-requests-remaining' => '98001'])]);
}

function syncWithSpreads(Competition $competition): void
{
    app(SyncOdds::class)->handle($competition, [MarketType::MatchResult, MarketType::Spreads]);
}

test('handicaps are bought from their own bookmakers and the main markets keep the region', function () {
    fakeOddsFeed(mainEvent(), spreadsEvent([]));

    app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals', 'spreads']);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => ($request->data()['regions'] ?? null) === 'uk' && $request['markets'] === 'h2h,totals' && ! isset($request['bookmakers']));
    Http::assertSent(fn (Request $request): bool => ($request->data()['bookmakers'] ?? null) === 'pinnacle,onexbet,marathonbet,bovada' && $request['markets'] === 'spreads' && ! isset($request['regions']));
    expect(OddsApiCall::sum('credits_spent'))->toBe(2)
        ->and(OddsApiCall::pluck('purpose')->unique()->all())->toBe([OddsApiPurpose::PreMatch]);
});

test('prices a whole or half handicap line enough books agree on, named from the home side', function () {
    // The bulk endpoint gives each book one spreads market: its main line.
    fakeOddsFeed(mainEvent(), spreadsEvent([
        'pinnacle' => [[-1.5, 2.6, 1.55]],
        'onexbet' => [[-1.5, 2.55, 1.56]],
        'marathonbet' => [[-1.5, 2.62, 1.52]],
        'bovada' => [[-1.0, 2.0, 1.8]],
        'coolbet' => [[-0.75, 1.9, 1.95]],
    ]));

    syncWithSpreads($this->competition);

    $fixture = Fixture::firstWhere('external_id', 'evt-ars-che');
    $markets = $fixture->markets()->where('type', MarketType::Spreads)->with('selections')->get()->keyBy('key');

    // -1.5 has three books; -1 has one (not created); the quarter line -0.75 is never offered.
    expect($markets->keys()->all())->toBe(['spreads:-1.5'])
        ->and($markets['spreads:-1.5']->status)->toBe(MarketStatus::Open)
        ->and($markets['spreads:-1.5']->bookmaker_count)->toBe(3)
        ->and($markets['spreads:-1.5']->name())->toBe('Handicap -1.5')
        ->and($markets['spreads:-1.5']->selections->pluck('name', 'outcome')->all())->toBe(['home' => 'Arsenal -1.5', 'away' => 'Chelsea +1.5'])
        ->and($markets['spreads:-1.5']->selections->every(fn ($selection) => $selection->price() !== null))->toBeTrue();
});

test('a level handicap is named without a sign', function () {
    fakeOddsFeed(mainEvent(), spreadsEvent([
        'pinnacle' => [[0, 1.45, 2.8]],
        'onexbet' => [[0, 1.47, 2.75]],
        'marathonbet' => [[0, 1.44, 2.85]],
    ]));

    syncWithSpreads($this->competition);

    $market = Market::where('key', 'spreads:0')->with('selections')->sole();
    expect($market->name())->toBe('Handicap 0')
        ->and($market->selections->pluck('name', 'outcome')->all())->toBe(['home' => 'Arsenal 0', 'away' => 'Chelsea 0']);
});

test('a book whose two sides do not mirror each other is ignored for that line', function () {
    $event = spreadsEvent([
        'pinnacle' => [[-1.5, 2.6, 1.55]],
        'onexbet' => [[-1.5, 2.55, 1.56]],
        'marathonbet' => [[-1.5, 2.62, 1.52]],
    ]);
    $event[0]['bookmakers'][2]['markets'][0]['outcomes'][1]['point'] = 1.0;
    fakeOddsFeed(mainEvent(), $event);

    syncWithSpreads($this->competition);

    expect(Market::where('type', MarketType::Spreads)->exists())->toBeFalse();
});

test('a failed handicap call keeps the 1X2 prices already bought', function () {
    Log::spy();
    fakeOddsFeed(mainEvent(), 500);

    syncWithSpreads($this->competition);

    expect(Fixture::firstWhere('external_id', 'evt-ars-che')->markets()->where('key', 'h2h')->sole()->status)->toBe(MarketStatus::Open)
        ->and(Market::where('type', MarketType::Spreads)->exists())->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '[spreads] not bought'))->once();
});

test('a failed main call still fails the sync', function () {
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response([], 500)]);

    expect(fn () => syncWithSpreads($this->competition))->toThrow(Exception::class);

    Http::assertSentCount(1);
});

test('quarter goal lines are never offered as totals', function () {
    $event = mainEvent();
    foreach ($event[0]['bookmakers'] as $i => $book) {
        $event[0]['bookmakers'][$i]['markets'][] = ['key' => 'totals', 'outcomes' => [['name' => 'Over', 'price' => 1.9, 'point' => 2.25], ['name' => 'Under', 'price' => 1.9, 'point' => 2.25]]];
    }
    fakeOddsFeed($event, spreadsEvent([]));

    app(SyncOdds::class)->handle($this->competition, [MarketType::MatchResult, MarketType::Totals]);

    expect(Market::where('key', 'totals:2.25')->exists())->toBeFalse();
});

test('lines are written the way players read them', function () {
    expect(Market::formatLine(-1.5))->toBe('-1.5')
        ->and(Market::formatLine(1.5, signed: true))->toBe('+1.5')
        ->and(Market::formatLine('0.00', signed: true))->toBe('0')
        ->and(Market::formatLine(-0.0))->toBe('0')
        ->and(Market::formatLine('2.50'))->toBe('2.5')
        ->and(Market::keyFor(MarketType::Spreads, 0.0))->toBe('spreads:0')
        ->and(Market::keyFor(MarketType::Spreads, -1.0))->toBe('spreads:-1')
        ->and(Market::keyFor(MarketType::Totals, 2.5))->toBe('totals:2.5')
        ->and(Market::isQuarterLine(-0.75))->toBeTrue()
        ->and(Market::isQuarterLine('2.25'))->toBeTrue()
        ->and(Market::isQuarterLine(-1.5))->toBeFalse()
        ->and(Market::isQuarterLine(2.0))->toBeFalse();
});

test('the match page has a Handicap tab with lines from lowest to highest', function () {
    $fixture = Fixture::factory()->for($this->competition)->create();
    foreach ([0.5, -1.5, 0.0, -0.5] as $line) {
        $market = Market::factory()->for($fixture)->create(['type' => MarketType::Spreads, 'line' => $line, 'key' => Market::keyFor(MarketType::Spreads, $line)]);
        $market->selections()->create(['outcome' => 'home', 'name' => 'Home', 'feed_price' => 1.9]);
    }

    $tabs = MarketTabs::group($fixture->markets()->with('selections')->get());

    expect($tabs->keys()->all())->toContain('handicap')
        ->and(MarketTabs::label('handicap'))->toBe('Handicap')
        ->and($tabs['handicap']->map->name()->all())->toBe(['Handicap -1.5', 'Handicap -0.5', 'Handicap 0', 'Handicap +0.5']);
});
