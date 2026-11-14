<?php

use App\Sportsbook\Models\OddsApiCall;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\CreditBudgetExceeded;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\OddsApi\OddsApiSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    config(['sportsbook.odds_api.monthly_credit_cap' => 500, 'sportsbook.odds_api.credit_reserve' => 50]);
});

test('records a free call and the quota headers it returned', function () {
    Http::fake(['odds-api.test/v4/sports*' => Http::response(oddsApiFixture('sports'), 200, [
        'x-requests-remaining' => '488', 'x-requests-used' => '12', 'x-requests-last' => '0',
    ])]);

    $sports = app(OddsApi::class)->sports();

    expect($sports)->toHaveCount(5);
    $this->assertDatabaseHas('sb_odds_api_calls', [
        'endpoint' => 'sports', 'credits_spent' => 0, 'credits_remaining' => 488, 'credits_used' => 12, 'http_status' => 200, 'succeeded' => true,
    ]);
});

test('requests decimal odds for the configured region and markets and records the credits spent', function () {
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response(oddsApiFixture('odds-soccer_epl'), 200, [
        'x-requests-remaining' => '486', 'x-requests-used' => '14', 'x-requests-last' => '2',
    ])]);

    app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals']);

    Http::assertSent(fn (Request $request): bool => $request['regions'] === 'uk'
        && $request['markets'] === 'h2h,totals'
        && $request['oddsFormat'] === 'decimal'
        && str_contains($request->url(), 'apiKey=test-api-key'));
    $this->assertDatabaseHas('sb_odds_api_calls', ['endpoint' => 'odds', 'sport_key' => 'soccer_epl', 'credits_spent' => 2, 'credits_remaining' => 486]);
});

test('refuses a paid call that would go over the monthly cap without contacting the API', function () {
    Http::fake();
    OddsApiCall::factory()->create(['credits_spent' => 499, 'credits_remaining' => 300]);

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals']))
        ->toThrow(CreditBudgetExceeded::class, '499 of the 500-credit monthly cap is already spent');

    Http::assertNothingSent();
    $this->assertDatabaseCount('sb_odds_api_calls', 1);
});

test('refuses a paid call that would dip into the reserve', function () {
    Http::fake();
    OddsApiCall::factory()->create(['credits_spent' => 0, 'credits_remaining' => 50]);

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h']))
        ->toThrow(CreditBudgetExceeded::class, 'only 50 credits remain and 50 are held in reserve');

    Http::assertNothingSent();
});

test('only counts this month\'s spend against the monthly cap', function () {
    $this->travelTo(Date::parse('2026-10-17 12:00:00', 'UTC'));
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response([], 200, ['x-requests-last' => '1', 'x-requests-remaining' => '400'])]);
    OddsApiCall::factory()->create(['credits_spent' => 500, 'created_at' => '2026-09-30 23:00:00']);

    app(OddsApi::class)->odds('soccer_epl', ['h2h']);

    Http::assertSentCount(1);
});

test('records a failed call and rethrows the error', function () {
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response(['message' => 'Usage quota has been reached.'], 401, ['x-requests-remaining' => '0'])]);

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h']))->toThrow(RequestException::class);

    $this->assertDatabaseHas('sb_odds_api_calls', ['endpoint' => 'odds', 'http_status' => 401, 'succeeded' => false, 'credits_remaining' => 0]);
});

test('never stores the API key in the ledger when a connection error quotes the URL', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://odds-api.test/v4/sports/soccer_epl/odds?apiKey=test-api-key'));

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h']))->toThrow(ConnectionException::class);

    $call = OddsApiCall::sole();
    expect($call->succeeded)->toBeFalse()
        ->and($call->error)->toContain('apiKey=[redacted]')
        ->and($call->error)->not->toContain('test-api-key');
});

test('records what each paid call was for, and free calls without a purpose', function () {
    Http::fake([
        'odds-api.test/v4/sports/soccer_epl/odds*' => Http::response([], 200, ['x-requests-last' => '1', 'x-requests-remaining' => '400']),
        'odds-api.test/v4/sports/soccer_epl/scores*' => Http::response([], 200, ['x-requests-last' => '2', 'x-requests-remaining' => '398']),
        'odds-api.test/v4/sports/soccer_epl/events*' => Http::response([], 200, ['x-requests-last' => '0']),
    ]);

    app(OddsApi::class)->odds('soccer_epl', ['h2h']);
    app(OddsApi::class)->odds('soccer_epl', ['h2h'], OddsApiPurpose::Live);
    app(OddsApi::class)->scores('soccer_epl', 3);
    app(OddsApi::class)->events('soccer_epl');

    expect(OddsApiCall::query()->orderBy('id')->pluck('purpose')->all())
        ->toBe([OddsApiPurpose::PreMatch, OddsApiPurpose::Live, OddsApiPurpose::Scores, null]);
});

test('refuses a paid call past its purpose\'s allowance while other purposes keep spending', function () {
    config(['sportsbook.odds_api.monthly_credit_cap' => 100_000, 'sportsbook.odds_api.allowances' => ['live' => 100, 'scores' => 50]]);
    Http::fake(['odds-api.test/v4/sports/soccer_epl/*' => Http::response([], 200, ['x-requests-last' => '1', 'x-requests-remaining' => '90000'])]);
    OddsApiCall::factory()->purpose(OddsApiPurpose::Live)->create(['credits_spent' => 99, 'credits_remaining' => 90_000]);

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals'], OddsApiPurpose::Live))
        ->toThrow(CreditBudgetExceeded::class, '99 of the 100-credit monthly live allowance is already spent');
    Http::assertNothingSent();

    app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals']);
    app(OddsApi::class)->scores('soccer_epl', 3);

    Http::assertSentCount(2);
});

test('a purpose without an allowance of its own is held only by the cap', function () {
    config(['sportsbook.odds_api.monthly_credit_cap' => 1_000, 'sportsbook.odds_api.allowances' => ['pre_match' => 10]]);
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response([], 200, ['x-requests-last' => '1', 'x-requests-remaining' => '900'])]);
    OddsApiCall::factory()->purpose(OddsApiPurpose::Recording)->create(['credits_spent' => 500]);

    app(OddsApi::class)->odds('soccer_epl', ['h2h'], OddsApiPurpose::Recording);

    Http::assertSentCount(1);
});

test('an allowance of 0 switches a purpose off', function () {
    config(['sportsbook.odds_api.allowances' => ['live' => 0]]);
    Http::fake();

    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h'], OddsApiPurpose::Live))->toThrow(CreditBudgetExceeded::class);

    Http::assertNothingSent();
});

test('named bookmakers replace the region and cost one region per ten books', function () {
    config(['sportsbook.odds_api.bookmakers' => 'pinnacle, betfair_ex_uk,williamhill,,pinnacle']);
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response([], 200, ['x-requests-last' => '2', 'x-requests-remaining' => '400'])]);

    app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals']);

    Http::assertSent(fn (Request $request): bool => $request['bookmakers'] === 'pinnacle,betfair_ex_uk,williamhill'
        && ! isset($request['regions']));
    expect(OddsApiSource::fromConfig()->creditsPerMarket())->toBe(1);

    config(['sportsbook.odds_api.bookmakers' => implode(',', array_map(fn (int $i): string => "book{$i}", range(1, 11)))]);
    expect(OddsApiSource::fromConfig()->creditsPerMarket())->toBe(2);

    config(['sportsbook.odds_api.bookmakers' => '', 'sportsbook.odds_api.region' => 'uk,eu']);
    expect(OddsApiSource::fromConfig()->creditsPerMarket())->toBe(2)
        ->and(OddsApiSource::fromConfig()->query())->toBe(['regions' => 'uk,eu']);
});

test('a refused call checks the cost of the books it would use', function () {
    config(['sportsbook.odds_api.bookmakers' => implode(',', array_map(fn (int $i): string => "book{$i}", range(1, 11))),
        'sportsbook.odds_api.allowances' => ['pre_match' => 3]]);
    Http::fake();

    // Two markets from 11 books = 2 × 2 = 4 credits, over an allowance of 3.
    expect(fn () => app(OddsApi::class)->odds('soccer_epl', ['h2h', 'totals']))->toThrow(CreditBudgetExceeded::class, 'Refusing a 4-credit');
});

test('usage this month is reported per purpose with each allowance', function () {
    $this->travelTo(Date::parse('2026-11-14 12:00:00', 'UTC'));
    config(['sportsbook.odds_api.allowances' => ['pre_match' => 9000, 'live' => 72000]]);
    OddsApiCall::factory()->create(['credits_spent' => 3]);
    OddsApiCall::factory()->purpose(OddsApiPurpose::Live)->create(['credits_spent' => 4]);
    OddsApiCall::factory()->purpose(OddsApiPurpose::Live)->create(['credits_spent' => 400, 'created_at' => '2026-10-31 23:00:00']);
    OddsApiCall::factory()->create(['credits_spent' => 0, 'purpose' => null, 'endpoint' => 'events']);

    expect(CreditBudget::fromConfig()->usageThisMonth())->toBe([
        'pre_match' => ['spent' => 3, 'allowance' => 9000],
        'per_event' => ['spent' => 0, 'allowance' => null],
        'live' => ['spent' => 4, 'allowance' => 72000],
        'scores' => ['spent' => 0, 'allowance' => null],
        'recording' => ['spent' => 0, 'allowance' => null],
    ]);
});
