<?php

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Sync\SyncFixtures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    Http::fake(['odds-api.test/v4/sports/soccer_epl/events*' => Http::response(oddsApiFixture('events-soccer_epl'))]);
});

test('creates fixtures from the feed', function () {
    $competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);

    app(SyncFixtures::class)->handle($competition);

    $fixture = Fixture::firstWhere('external_id', 'evt-arsenal-spurs');
    expect(Fixture::count())->toBe(2)
        ->and($fixture->name())->toBe('Arsenal v Tottenham Hotspur')
        ->and($fixture->kicks_off_at->toIso8601ZuluString())->toBe('2026-10-20T14:00:00Z')
        ->and($fixture->competition->is($competition))->toBeTrue();
});

test('updates a moved kick-off time without creating a duplicate fixture', function () {
    $competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
    $existing = Fixture::factory()->for($competition)->create(['external_id' => 'evt-arsenal-spurs', 'kicks_off_at' => '2026-10-19 12:00:00']);

    app(SyncFixtures::class)->handle($competition);

    expect(Fixture::where('external_id', 'evt-arsenal-spurs')->count())->toBe(1)
        ->and($existing->fresh()->kicks_off_at->toIso8601ZuluString())->toBe('2026-10-20T14:00:00Z');
});

/*
 * Sentry KADI-B: one fixture lookup per feed event. The existing fixtures are read in one query.
 */
test('reads the existing fixtures in one query, however many events the feed has', function () {
    $competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
    app(SyncFixtures::class)->handle($competition);

    DB::enableQueryLog();
    app(SyncFixtures::class)->handle($competition);

    $lookups = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "sb_fixtures"'));
    expect($lookups)->toHaveCount(1)
        ->and(Fixture::count())->toBe(2);
});

test('the same event listed twice in one feed is still one fixture', function () {
    $competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_spain_la_liga']);
    $events = oddsApiFixture('events-soccer_epl');
    Http::fake(['odds-api.test/v4/sports/soccer_spain_la_liga/events*' => Http::response([...$events, $events[0]])]);

    expect(app(SyncFixtures::class)->handle($competition))->toBe(3)
        ->and(Fixture::count())->toBe(2);
});
