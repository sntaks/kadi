<?php

use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Models\WagerOp;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Phase 10: GMS runs sports operations through /api/v1/backoffice/sports (docs/backoffice-api.md). Requests are
 * HMAC-signed over timestamp, request id, method, path and body; writes are idempotent by request id; every
 * action goes through the same domain code, so settlements still leave through the wager-ops outbox.
 */

const BACKOFFICE_SECRET = 'gms-backoffice-secret-for-tests';
const BACKOFFICE_STAFF = ['id' => '12', 'name' => 'Jane Trader', 'email' => 'jane@gms.test'];

beforeEach(function () {
    config(['kadi.backoffice.secrets' => [BACKOFFICE_SECRET]]);
});

/**
 * A signed call, as GMS makes it.
 *
 * @param  array<string, mixed>|null  $body
 */
function backoffice(string $method, string $path, ?array $body = null, ?string $requestId = null, ?int $timestamp = null, string $secret = BACKOFFICE_SECRET): TestResponse
{
    $uri = '/api/v1/backoffice/sports/'.ltrim($path, '/');
    $raw = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp ??= time();
    $requestId ??= (string) Str::uuid();

    return test()->call($method, $uri, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_BACKOFFICE_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_BACKOFFICE_REQUEST_ID' => $requestId,
        'HTTP_X_BACKOFFICE_SIGNATURE' => hash_hmac('sha256', implode('.', [$timestamp, $requestId, strtoupper($method), $uri, hash('sha256', $raw)]), $secret),
    ], content: $raw);
}

/** @param  array<string, mixed>  $body */
function staffBody(array $body = []): array
{
    return [...$body, 'staff' => BACKOFFICE_STAFF];
}

test('the API is closed without secrets, and refuses unsigned, wrongly signed or stale calls', function () {
    $market = pricedFixture()->matchResultMarket;

    config(['kadi.backoffice.secrets' => []]);
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'x']))->assertStatus(503);

    config(['kadi.backoffice.secrets' => [BACKOFFICE_SECRET]]);
    $this->postJson("/api/v1/backoffice/sports/markets/{$market->id}/suspend", staffBody(['reason' => 'x']))->assertUnauthorized();
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'x']), secret: 'wrong')->assertUnauthorized();
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'x']), timestamp: time() - 600)->assertUnauthorized();
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'x']), requestId: 'bad id!')->assertUnauthorized();

    expect($market->fresh()->status)->toBe(MarketStatus::Open);
});

test('a signature is bound to its method and path: it cannot be replayed on another endpoint', function () {
    $market = pricedFixture()->matchResultMarket;
    $body = json_encode(staffBody(['reason' => 'Late news']));
    $ts = (string) time();
    $id = (string) Str::uuid();
    $signature = hash_hmac('sha256', implode('.', [$ts, $id, 'POST', "/api/v1/backoffice/sports/markets/{$market->id}/suspend", $body]), BACKOFFICE_SECRET);

    $this->call('POST', "/api/v1/backoffice/sports/markets/{$market->id}/reopen", server: [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_BACKOFFICE_TIMESTAMP' => $ts, 'HTTP_X_BACKOFFICE_REQUEST_ID' => $id, 'HTTP_X_BACKOFFICE_SIGNATURE' => $signature,
    ], content: $body)->assertUnauthorized();
});

test('any of the configured secrets is accepted, so a secret can be rotated', function () {
    config(['kadi.backoffice.secrets' => ['old-secret', BACKOFFICE_SECRET]]);
    $market = pricedFixture()->matchResultMarket;

    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'x']), secret: 'old-secret')->assertOk();
});

test('a retried write with the same request id returns the first answer and acts once', function () {
    $market = pricedFixture()->matchResultMarket;
    $id = (string) Str::uuid();

    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Late news']), requestId: $id)->assertOk();
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Late news']), requestId: $id)
        ->assertOk()->assertHeader('Backoffice-Replayed', 'true');

    expect(Activity::query()->where('event', 'market-suspended')->count())->toBe(1);

    // The same id for a different request is refused, never answered with the first call's response.
    backoffice('POST', "markets/{$market->id}/reopen", staffBody(), requestId: $id)->assertUnprocessable()->assertJsonPath('code', 'request_id_reused');
});

test('a refused write is not kept, so a retry with the same request id runs again', function () {
    $market = pricedFixture()->matchResultMarket;
    $id = (string) Str::uuid();

    config(['kadi.backoffice.per_minute' => 1]);
    backoffice('GET', 'odds-api/credits')->assertOk();
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Late news']), requestId: $id)->assertTooManyRequests();

    RateLimiter::clear(md5('backoffice'.'gms'));
    config(['kadi.backoffice.per_minute' => 300]);
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Late news']), requestId: $id)
        ->assertOk()->assertHeaderMissing('Backoffice-Replayed');

    expect($market->fresh()->status)->toBe(MarketStatus::Suspended);
});

test('staff can record a basketball score over 99, but a football score stops at 99', function () {
    $football = pricedFixture(['kicks_off_at' => now('UTC')->subHours(3)]);
    $basketball = Fixture::factory()->create([
        'kicks_off_at' => now('UTC')->subHours(3),
        'competition_id' => Competition::factory()->synced()->for(Sport::factory()->configured('basketball')),
    ]);

    backoffice('POST', "fixtures/{$basketball->id}/result", staffBody(['home_score' => 118, 'away_score' => 112]))->assertOk();
    backoffice('POST', "fixtures/{$football->id}/result", staffBody(['home_score' => 118, 'away_score' => 112]))->assertUnprocessable();

    expect($basketball->result()->value('home_score'))->toBe(118);
});

test('a void result may be sent as 1 as well as true', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->subHours(3)]);

    backoffice('POST', "fixtures/{$fixture->id}/result", staffBody(['is_void' => 1]))->assertOk();
});

test('writes need the staff member acting, who is named in the activity log', function () {
    $market = pricedFixture()->matchResultMarket;

    backoffice('POST', "markets/{$market->id}/suspend", ['reason' => 'Late news'])->assertUnprocessable()->assertJsonValidationErrors('staff');
    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Late news']))->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(Activity::query()->latest('id')->first()->properties->get('staff'))->toMatchArray(['source' => 'gms', 'id' => '12', 'name' => 'Jane Trader']);
});

test('a trader can suspend and reopen a market, and override and clear prices', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $market = $fixture->matchResultMarket;
    $home = selectionFor($fixture, 'home');

    backoffice('POST', "markets/{$market->id}/suspend", staffBody(['reason' => 'Injury news']))->assertOk();
    backoffice('POST', "markets/{$market->id}/reopen", staffBody())->assertOk()->assertJsonPath('data.status', 'open');

    backoffice('POST', "markets/{$market->id}/prices", staffBody(['prices' => [$home->id => '1.95'], 'reason' => 'Liability']))
        ->assertOk()->assertJsonPath('data.selections.0.price', '1.95');
    expect($home->fresh())->override_by_staff->toBe('Jane Trader (GMS #12)')->override_by_admin_id->toBeNull();

    backoffice('POST', "markets/{$market->id}/prices", staffBody(['prices' => [$home->id => '0.50'], 'reason' => 'x']))->assertUnprocessable();
    backoffice('POST', "markets/{$market->id}/prices", staffBody(['prices' => [999999 => '2.00'], 'reason' => 'x']))->assertUnprocessable();
    backoffice('POST', "markets/{$market->id}/prices", staffBody(['prices' => [$home->id => null], 'reason' => 'Back to feed']))->assertOk();

    expect($home->fresh())->override_price->toBeNull()->override_by_staff->toBeNull();
});

test('recording a result settles the bets through the Kadi API outbox', function () {
    $api = wagerApi();
    $player = $api->player(100_000);
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $bet = betOn($player, [[$fixture, 'home']]);

    backoffice('POST', "fixtures/{$fixture->id}/result", staffBody(['home_score' => 2, 'away_score' => 0, 'note' => 'From the referee report']))
        ->assertOk()->assertJsonPath('data.bets_settled', 1);

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and(WagerOp::sole()->status)->toBe(WagerOp::SENT)
        ->and($api->balanceOf($player))->toBe(111_000)
        ->and(FixtureResult::sole())->source->toBe(FixtureResult::SOURCE_ADMIN)->recorded_by_staff->toBe('Jane Trader (GMS #12)');

    // A correction reverses and settles version 2.
    backoffice('POST', "fixtures/{$fixture->id}/result", staffBody(['home_score' => 0, 'away_score' => 1]))->assertOk();
    expect($bet->fresh()->status)->toBe(BetStatus::Lost)->and($api->balanceOf($player))->toBe(90_000);
});

test('a result needs scores unless the match is void', function () {
    $fixture = pricedFixture();

    backoffice('POST', "fixtures/{$fixture->id}/result", staffBody(['home_score' => 1]))->assertUnprocessable()->assertJsonValidationErrors('away_score');
    backoffice('POST', "fixtures/{$fixture->id}/result", staffBody(['is_void' => true]))->assertOk();

    expect($fixture->fresh()->result->is_void)->toBeTrue();
});

test('staff can void a leg and cancel an open bet, both paid through the outbox', function () {
    $api = wagerApi();
    $player = $api->player(100_000);
    $first = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $second = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $multi = betOn($player, [[$first, 'home'], [$second, 'home']]);
    recordScore($first, 1, 0);

    backoffice('POST', 'bet-legs/'.$multi->legs()->where('fixture_id', $second->id)->value('id').'/void', staffBody(['reason' => 'Priced in error']))
        ->assertOk()->assertJsonPath('data.bet.status', 'won');

    $single = betOn($player, [[pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);
    backoffice('POST', "bets/{$single->id}/cancel", staffBody(['reason' => 'Placed in error']))->assertOk()->assertJsonPath('data.bet.status', 'cancelled');
    backoffice('POST', "bets/{$single->id}/cancel", staffBody(['reason' => 'Again']))->assertUnprocessable();

    expect($api->balanceOf($player))->toBe(100_000 - 10_000 + 21_000);
});

test('a failed wager op can be put back in line', function () {
    $api = wagerApi();
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    betOn($api->player(), [[$fixture, 'home']]);
    $api->next('settle', [422, 'payout_above_limit']);
    recordScore($fixture, 1, 0);
    $op = WagerOp::sole();

    expect($op->status)->toBe(WagerOp::FAILED);

    backoffice('GET', 'reconciliation')->assertOk()->assertJsonPath('data.needs_operator.ops.0.id', $op->id);
    backoffice('POST', "wager-ops/{$op->id}/retry", staffBody())->assertOk()->assertJsonPath('data.status', WagerOp::SENT);
    backoffice('POST', "wager-ops/{$op->id}/retry", staffBody())->assertConflict();
});

test('staff can draft, change, publish and cancel a jackpot', function () {
    config(['sportsbook.jackpot.min_matches' => 3, 'sportsbook.jackpot.max_matches' => 5]);
    $fixtures = collect(range(1, 3))->map(fn ($i) => Fixture::factory()->create(['kicks_off_at' => now('UTC')->addDays(2)->addHours($i)]));
    $draft = ['name' => 'Weekend Jackpot', 'entry_price' => 9_900, 'void_limit' => 1, 'prize_tiers' => [['correct' => 3, 'pool' => 1_000_000]], 'fixture_ids' => $fixtures->pluck('id')->all()];

    $id = backoffice('POST', 'jackpots', staffBody($draft))->assertCreated()->assertJsonPath('data.jackpot.matches', 3)->json('data.jackpot.id');
    backoffice('PUT', "jackpots/{$id}", staffBody([...$draft, 'name' => 'Big Weekend']))->assertOk()->assertJsonPath('data.jackpot.name', 'Big Weekend');
    backoffice('POST', "jackpots/{$id}/publish", staffBody())->assertOk()->assertJsonPath('data.jackpot.status', 'open');
    backoffice('GET', "jackpots/{$id}/tiers")->assertOk()->assertJsonPath('data.tiers.0.correct', 3);
    backoffice('POST', "jackpots/{$id}/cancel", staffBody(['reason' => 'Feed problems']))->assertOk()->assertJsonPath('data.jackpot.status', 'cancelled');

    expect(Jackpot::find($id)->status)->toBe(JackpotStatus::Cancelled);
});

test('competition syncing and order can be changed', function () {
    $competition = pricedFixture()->competition;

    backoffice('PATCH', "competitions/{$competition->id}", staffBody(['is_synced' => false, 'sort_order' => 5]))
        ->assertOk()->assertJsonPath('data.competition.is_synced', false)->assertJsonPath('data.competition.sort_order', 5);
});

test('exposure, odds API credits and the day\'s reconciliation are served with this app\'s rules', function () {
    $api = wagerApi();
    betOn($api->player(), [[pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);

    backoffice('GET', 'exposure?limit=10')->assertOk()->assertJsonPath('data.matches.0.bets', 1);
    backoffice('GET', 'odds-api/credits')->assertOk()
        ->assertJsonStructure(['data' => ['spent_this_month', 'remaining', 'monthly_cap', 'reserve', 'purposes' => ['pre_match' => ['spent', 'allowance'], 'per_event', 'live', 'scores', 'recording']]])
        ->assertJsonPath('data.purposes.live.allowance', 72000)
        ->assertJsonPath('data.purposes.recording.allowance', null);
    backoffice('GET', 'reconciliation?date='.now(config('sportsbook.timezone'))->toDateString())
        ->assertOk()
        ->assertJsonPath('data.products.sportsbook.placed.open', 1)
        ->assertJsonPath('data.products.sportsbook.cash_stakes', 10_000);
});

test('unknown records are a JSON 404', function () {
    backoffice('POST', 'bets/999999/cancel', staffBody(['reason' => 'x']))->assertNotFound()->assertJson(['message' => true]);
});
