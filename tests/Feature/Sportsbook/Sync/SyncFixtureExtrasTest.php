<?php

use App\Jobs\Sportsbook\SyncFixtureExtrasJob;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\OddsApiCall;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\Pricing\Derived\DeriveMarkets;
use App\Sportsbook\Sync\SyncFixtureExtras;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/*
 * Plan §10.6 (phase 2): each match's extra markets from the event endpoint: alternate goal lines and handicaps, and
 * team goal lines, from 10 named books, once in each window before kick-off.
 */

const EXTRAS = ['alternate_spreads', 'alternate_totals', 'alternate_team_totals'];

beforeEach(function () {
    useLiveOddsApi();
    config([
        'sportsbook.odds.margin' => 0.06,
        'sportsbook.odds.minimum_bookmakers' => 3,
        'sportsbook.per_event.bookmakers' => 'pinnacle,bovada,fanduel',
        'sportsbook.per_event.competitions' => ['soccer_epl'],
    ]);
    $this->travelTo(Date::parse('2026-11-20 12:00:00', 'UTC'));
    // One fake for the event endpoint, answering with whatever fakeExtras() set last: a second Http::fake() for the
    // same URL would not replace the first.
    $this->extrasAnswer = [[], 200];
    Http::fake(['odds-api.test/v4/sports/soccer_epl/events/evt-mci-ful/odds*' => fn () => Http::response($this->extrasAnswer[0], $this->extrasAnswer[1], ['x-requests-last' => '3', 'x-requests-remaining' => '90000'])]);
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
    $this->fixture = Fixture::factory()->for($this->competition)->create([
        'external_id' => 'evt-mci-ful', 'home_team' => 'Manchester City', 'away_team' => 'Fulham', 'kicks_off_at' => Date::parse('2026-11-21 12:30:00', 'UTC'),
    ]);
});

/**
 * The event endpoint's answer: the same alternate lines from each named book.
 *
 * @param  list<string>  $books
 * @param  array{totals?: list<float>, spreads?: list<float>, team?: array<string, list<float>>}  $lines
 */
function extrasAnswer(array $books, array $lines): array
{
    $markets = [];

    if (isset($lines['totals'])) {
        $markets[] = ['key' => 'alternate_totals', 'outcomes' => collect($lines['totals'])->flatMap(fn (float $point) => [
            ['name' => 'Over', 'price' => 1.9, 'point' => $point], ['name' => 'Under', 'price' => 1.9, 'point' => $point],
        ])->all()];
    }

    if (isset($lines['spreads'])) {
        $markets[] = ['key' => 'alternate_spreads', 'outcomes' => collect($lines['spreads'])->flatMap(fn (float $point) => [
            ['name' => 'Manchester City', 'price' => 1.9, 'point' => $point], ['name' => 'Fulham', 'price' => 1.9, 'point' => -$point],
        ])->all()];
    }

    if (isset($lines['team'])) {
        $markets[] = ['key' => 'alternate_team_totals', 'outcomes' => collect($lines['team'])->flatMap(fn (array $points, string $team) => collect($points)->flatMap(fn (float $point) => [
            ['name' => 'Over', 'description' => $team, 'price' => 1.9, 'point' => $point], ['name' => 'Under', 'description' => $team, 'price' => 1.9, 'point' => $point],
        ]))->all()];
    }

    return ['id' => 'evt-mci-ful', 'sport_key' => 'soccer_epl', 'commence_time' => '2026-11-21T12:30:00Z', 'home_team' => 'Manchester City', 'away_team' => 'Fulham',
        'bookmakers' => array_map(fn (string $book): array => ['key' => $book, 'markets' => $markets], $books)];
}

/** Sets what the event endpoint answers next (beforeEach registers one fake that reads it). */
function fakeExtras(array $answer, int $status = 200): void
{
    test()->extrasAnswer = [$answer, $status];
}

function syncExtras(Fixture $fixture): int
{
    return app(SyncFixtureExtras::class)->handle($fixture, EXTRAS);
}

test('buys a match\'s extras from the named books and counts them as per-event spend', function () {
    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [1.5]]));

    syncExtras($this->fixture);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sports/soccer_epl/events/evt-mci-ful/odds')
        && $request['bookmakers'] === 'pinnacle,bovada,fanduel'
        && $request['markets'] === 'alternate_spreads,alternate_totals,alternate_team_totals'
        && ! isset($request->data()['regions']));
    expect(OddsApiCall::sole())->purpose->toBe(OddsApiPurpose::PerEvent)->credits_spent->toBe(3)->endpoint->toBe('event-odds')
        ->and($this->fixture->fresh()->extras_synced_at)->not->toBeNull();
});

test('prices every whole and half alternate line three books agree on, as extra markets', function () {
    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], [
        'totals' => [0.5, 1.5, 2.25, 3.5],
        'spreads' => [-2.5, -1.75, -1.0],
        'team' => ['Manchester City' => [1.5, 2.5], 'Fulham' => [0.5]],
    ]));

    expect(syncExtras($this->fixture))->toBe(8);

    $markets = $this->fixture->markets()->with('selections')->get()->keyBy('key');
    expect($markets->keys()->sort()->values()->all())->toBe([
        'away_team_totals:0.5', 'home_team_totals:1.5', 'home_team_totals:2.5', 'spreads:-1', 'spreads:-2.5', 'totals:0.5', 'totals:1.5', 'totals:3.5',
    ])
        ->and($markets->every(fn (Market $market) => $market->source === MarketSource::EventFeed && $market->status === MarketStatus::Open))->toBeTrue()
        ->and($markets['home_team_totals:1.5']->setRelation('fixture', $this->fixture)->name())->toBe('Manchester City goals 1.5')
        ->and($markets['away_team_totals:0.5']->setRelation('fixture', $this->fixture)->name())->toBe('Fulham goals 0.5')
        ->and($markets['home_team_totals:1.5']->selections->pluck('name', 'outcome')->all())->toBe(['over' => 'Over 1.5', 'under' => 'Under 1.5'])
        ->and($markets['spreads:-2.5']->selections->pluck('name', 'outcome')->all())->toBe(['home' => 'Manchester City -2.5', 'away' => 'Fulham +2.5']);
});

test('a line fewer than three books price is not offered', function () {
    fakeExtras(extrasAnswer(['pinnacle', 'bovada'], ['totals' => [1.5]]));

    expect(syncExtras($this->fixture))->toBe(0)
        ->and(Market::count())->toBe(0);
});

test('the bulk feed keeps its main line, and takes over an extra line it starts pricing', function () {
    $main = Market::factory()->for($this->fixture)->create(['type' => MarketType::Totals, 'line' => 2.5, 'key' => 'totals:2.5', 'source' => MarketSource::Feed]);
    $main->selections()->create(['outcome' => 'over', 'name' => 'Over 2.5', 'feed_price' => '1.55']);
    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [2.5, 3.5]]));

    syncExtras($this->fixture);

    expect($main->fresh()->source)->toBe(MarketSource::Feed)
        ->and($main->selections()->value('feed_price'))->toBe('1.55')
        ->and(Market::firstWhere('key', 'totals:3.5')->source)->toBe(MarketSource::EventFeed);
});

test('an extra line the books drop is suspended, and reopens when priced again', function () {
    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [1.5, 3.5]]));
    syncExtras($this->fixture);

    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [1.5]]));
    syncExtras($this->fixture);

    expect(Market::firstWhere('key', 'totals:3.5'))->status->toBe(MarketStatus::Suspended)->suspension_reason->toBe(SuspensionReason::InsufficientBookmakers)
        ->and(Market::firstWhere('key', 'totals:1.5')->status)->toBe(MarketStatus::Open);

    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [1.5, 3.5]]));
    syncExtras($this->fixture);

    expect(Market::firstWhere('key', 'totals:3.5')->status)->toBe(MarketStatus::Open);
});

test('a match the API no longer has costs nothing and leaves no extras open', function () {
    fakeExtras(extrasAnswer(['pinnacle', 'bovada', 'fanduel'], ['totals' => [1.5]]));
    syncExtras($this->fixture);
    fakeExtras(['message' => 'Event not found'], 404);

    expect(app(OddsApi::class)->eventOdds('soccer_epl', 'evt-mci-ful', EXTRAS))->toBeNull()
        ->and(syncExtras($this->fixture))->toBe(0)
        ->and(Market::firstWhere('key', 'totals:1.5')->status)->toBe(MarketStatus::Suspended);
});

test('derived goal lines never overwrite an extra line', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addDay()]);
    $fixture->matchResultMarket->update(['priced_at' => now('UTC')]);
    $fixture->matchResultMarket->selections->each(fn ($selection) => $selection->update(['fair_probability' => ['home' => 0.45, 'draw' => 0.28, 'away' => 0.27][$selection->outcome]]));
    $extra = Market::factory()->for($fixture)->create(['type' => MarketType::Totals, 'line' => 1.5, 'key' => 'totals:1.5', 'source' => MarketSource::EventFeed]);
    $extra->selections()->create(['outcome' => 'over', 'name' => 'Over 1.5', 'feed_price' => '1.30']);

    app(DeriveMarkets::class)->handle($fixture);

    expect($extra->fresh()->source)->toBe(MarketSource::EventFeed)
        ->and($extra->selections()->value('feed_price'))->toBe('1.30')
        ->and(Market::where('fixture_id', $fixture->id)->where('source', MarketSource::Derived)->exists())->toBeTrue();
});

test('the hourly run buys each match once in each window before kick-off', function () {
    Queue::fake([SyncFixtureExtrasJob::class]);
    $other = Fixture::factory()->for(Competition::factory()->synced()->create(['odds_api_key' => 'soccer_spl']))->create(['kicks_off_at' => Date::parse('2026-11-21 12:30:00', 'UTC')]);

    // 24.5 h before kick-off: inside the day-before window. Other competitions are not bought.
    $this->artisan('sportsbook:sync-event-odds')->assertSuccessful();
    Queue::assertPushed(SyncFixtureExtrasJob::class, fn (SyncFixtureExtrasJob $job): bool => $job->fixture->is($this->fixture) && $job->markets === EXTRAS);
    Queue::assertPushed(SyncFixtureExtrasJob::class, 1);
    expect($other)->not->toBeNull();

    // Bought in this window: the next hours buy nothing until the next window opens.
    $this->fixture->update(['extras_synced_at' => now('UTC')]);
    Queue::fake([SyncFixtureExtrasJob::class]);
    $this->travelTo(Date::parse('2026-11-20 20:00:00', 'UTC'));
    $this->artisan('sportsbook:sync-event-odds')->assertSuccessful();
    Queue::assertNothingPushed();

    // Between the windows (12 h before): nothing.
    $this->travelTo(Date::parse('2026-11-21 00:30:00', 'UTC'));
    $this->artisan('sportsbook:sync-event-odds')->assertSuccessful();
    Queue::assertNothingPushed();

    // 2 h before kick-off: bought once more.
    $this->travelTo(Date::parse('2026-11-21 10:30:00', 'UTC'));
    $this->artisan('sportsbook:sync-event-odds')->assertSuccessful();
    Queue::assertPushed(SyncFixtureExtrasJob::class, 1);
});

test('the switch turns match extras off', function () {
    config(['sportsbook.per_event.enabled' => false]);
    Queue::fake([SyncFixtureExtrasJob::class]);

    $this->artisan('sportsbook:sync-event-odds')->expectsOutputToContain('switched off')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('past the per-event allowance the job logs and stops', function () {
    config(['sportsbook.odds_api.allowances.per_event' => 2]);
    Http::fake();
    Log::spy();

    SyncFixtureExtrasJob::dispatchSync($this->fixture, EXTRAS);

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Match extras not bought'))->once();
});
