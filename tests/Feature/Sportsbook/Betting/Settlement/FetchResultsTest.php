<?php

use App\Sportsbook\Betting\Settlement\FetchResults;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/*
 * Ported from the standalone sportsbook. Bets are placed through the fake Kadi wager API; only Odds API scores calls
 * are counted.
 */

beforeEach(function () {
    $this->api = wagerApi();
    useLiveOddsApi();
    Http::fake(['odds-api.test/v4/sports/*/scores*' => Http::response(oddsApiFixture('scores-soccer_epl'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '484'])]);
    $this->travelTo(Date::parse('2026-10-17 10:00:00', 'UTC'));
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
});

/** The Odds API scores calls made so far. */
function scoresCalls(): int
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/scores'))->count();
}

/**
 * A fixture from the recorded scores feed (Everton 0-2 Chelsea by default), with a bet on it placed before kick-off.
 *
 * @param  array<string, mixed>  $attributes
 */
function feedFixtureWithBet(Competition $competition, array $attributes = [], string $outcome = 'away'): Fixture
{
    $fixture = pricedFixture([
        'competition_id' => $competition->id,
        'external_id' => '764e6513cb3f348881503df70ccb90e0',
        'home_team' => 'Everton',
        'away_team' => 'Chelsea',
        'kicks_off_at' => '2026-10-17 11:30:00',
        ...$attributes,
    ]);
    betOn(test()->api->player(), [[$fixture, $outcome]]);

    return $fixture;
}

test('fetches scores for a competition with matches waiting on a result and settles them', function () {
    $fixture = feedFixtureWithBet($this->competition);
    $notFinished = feedFixtureWithBet($this->competition, ['external_id' => 'dfd5d6648be46426e418801584d46a68', 'home_team' => 'Bournemouth', 'away_team' => 'Sunderland']);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 1, 'results' => 1]);

    expect($fixture->result)->home_score->toBe(0)->away_score->toBe(2)->source->toBe(FixtureResult::SOURCE_FEED)
        ->and($fixture->betLegs->sole()->bet->status)->toBe(BetStatus::Won)
        ->and($notFinished->result()->exists())->toBeFalse()
        ->and($this->competition->fresh()->scores_checked_at->equalTo(now()))->toBeTrue()
        ->and(OddsApiCall::sole()->credits_spent)->toBe(2)
        ->and($this->api->callsTo('settle'))->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sports/soccer_epl/scores') && str_contains($request->url(), 'daysFrom=3'));
});

test('spends no credits until a match with open bets has had time to finish', function () {
    feedFixtureWithBet($this->competition, ['kicks_off_at' => '2026-10-17 11:30:00']);
    $this->travelTo(Date::parse('2026-10-17 13:30:00', 'UTC'));

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 0, 'results' => 0])
        ->and(scoresCalls())->toBe(0);
});

test('spends no credits on matches without open bets, with a result already, or beyond the feed\'s three days', function () {
    pricedFixture(['competition_id' => $this->competition->id, 'kicks_off_at' => '2026-10-17 11:30:00']);
    FixtureResult::factory()->for(feedFixtureWithBet($this->competition, ['external_id' => 'already-settled']))->create();
    feedFixtureWithBet($this->competition, ['external_id' => 'too-old', 'kicks_off_at' => '2026-10-17 11:30:00']);
    $this->travelTo(Date::parse('2026-10-20 12:00:00', 'UTC'));

    expect(app(FetchResults::class)->handle()['competitions'])->toBe(0)
        ->and(scoresCalls())->toBe(0);
});

test('asks the feed about a competition at most once every 12 hours', function () {
    feedFixtureWithBet($this->competition, ['external_id' => 'not-in-the-feed']);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));

    app(FetchResults::class)->handle();
    $this->travelTo(Date::parse('2026-10-18 20:59:00', 'UTC'));
    app(FetchResults::class)->handle();
    $this->travelTo(Date::parse('2026-10-18 21:00:00', 'UTC'));
    app(FetchResults::class)->handle();

    expect(scoresCalls())->toBe(2);
});

test('leaves competitions set to manual settlement to the admins', function () {
    $championsLeague = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_uefa_champs_league']);
    feedFixtureWithBet($championsLeague);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));

    expect($championsLeague->auto_settle)->toBeFalse()
        ->and(app(FetchResults::class)->handle()['competitions'])->toBe(0)
        ->and(scoresCalls())->toBe(0);
});

test('stops when the monthly credit budget would be exceeded', function () {
    config(['sportsbook.odds_api.monthly_credit_cap' => 1]);
    feedFixtureWithBet($this->competition);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 0, 'results' => 0])
        ->and(scoresCalls())->toBe(0);
});

test('the settle command runs hourly and settles from the feed', function () {
    $fixture = feedFixtureWithBet($this->competition);
    $this->travelTo(Date::parse('2026-10-18 09:00:00', 'UTC'));

    $this->artisan('sportsbook:settle')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'sportsbook:settle'));
    expect($fixture->result()->exists())->toBeTrue()
        ->and($event?->expression)->toBe('0 * * * *');
});
