<?php

use App\Models\User;
use App\Sportsbook\Betting\BetNotPlaced;
use App\Sportsbook\Betting\Settlement\CancelBet;
use App\Sportsbook\Betting\Settlement\RecordResult;
use App\Sportsbook\Betting\Settlement\VoidLeg;
use App\Sportsbook\Betting\Settlement\VoidPostponedLegs;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Enums\FreeBetSource;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\Wagers\WagerOps;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
 * Ported from the standalone sportsbook's settlement tests: the decisions are unchanged, and the money now moves at
 * the Kadi API through the sb_wager_ops outbox (plan §5.3). Balances are the fake API's.
 */

beforeEach(function () {
    $this->api = wagerApi();
});

test('a winning single pays stake times odds at the API', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    recordScore($fixture, 2, 0);

    expect($bet->fresh())
        ->status->toBe(BetStatus::Won)
        ->payout->toBe(21_000)
        ->tax->toBe(0)
        ->settled_at->not->toBeNull()
        ->and($this->api->balanceOf($player))->toBe(111_000)
        ->and($this->api->callsTo('settle')[0]['body'])->toBe(['product' => 'sportsbook', 'version' => 1, 'outcome' => 'won', 'payout' => '210.00'])
        ->and(WagerOp::sole()->status)->toBe(WagerOp::SENT);
    expect($bet->fresh()->legs->sole()->status)->toBe(BetLegStatus::Won);
});

test('a losing single pays nothing', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    recordScore($fixture, 0, 1);

    expect($bet->fresh())->status->toBe(BetStatus::Lost)->payout->toBe(0)
        ->and($this->api->wager('sportsbook', $bet->wager_reference)['status'])->toBe('lost')
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('a multi is lost as soon as one leg loses, with other matches still to play', function () {
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($this->api->player(), [[$first, 'home'], [$second, 'home']]);

    recordScore($first, 0, 2);

    expect($bet->fresh()->status)->toBe(BetStatus::Lost)
        ->and($bet->fresh()->legs->pluck('status')->all())->toBe([BetLegStatus::Lost, BetLegStatus::Open]);
});

test('a multi waits for every leg, then pays the combined odds of the winning legs', function () {
    $player = $this->api->player(100_000);
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($player, [[$first, 'home'], [$second, 'draw']]);

    recordScore($first, 1, 0);

    expect($bet->fresh()->status)->toBe(BetStatus::Open)
        ->and($this->api->callsTo('settle'))->toBe([]);

    recordScore($second, 2, 2);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(71_400)
        ->and($this->api->balanceOf($player))->toBe(161_400);
});

test('a multibet bonus is sent as the bonus, apart from the payout', function () {
    $player = $this->api->player(100_000);
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn($player, [[$fixtures[0], 'home'], [$fixtures[1], 'home'], [$fixtures[2], 'home']]);

    foreach ($fixtures as $fixture) {
        recordScore($fixture, 1, 0);
    }

    // 100 × 9.26 (2.10³, floored) = 926, plus 3% of the 826 profit.
    expect($this->api->callsTo('settle')[0]['body'])->toMatchArray(['outcome' => 'won', 'payout' => '926.00', 'bonus' => '24.78'])
        ->and($bet->fresh())->bonus->toBe(2_478)->payout->toBe(95_078)
        ->and($this->api->balanceOf($player))->toBe(185_078);
});

test('a void leg counts as 1.00 in a multi', function () {
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($this->api->player(), [[$first, 'home'], [$second, 'home']]);

    recordScore($first, 1, 0);
    app(RecordResult::class)->handle($second, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(21_000);
});

test('when every leg is void the API gives the stake back and the bet is void', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    expect($bet->fresh())->status->toBe(BetStatus::Void)->payout->toBe(10_000)
        ->and($this->api->callsTo('settle')[0]['body'])->toMatchArray(['outcome' => 'void', 'payout' => '0.00'])
        ->and($this->api->balanceOf($player))->toBe(100_000)
        ->and($fixture->fresh()->status)->toBe(FixtureStatus::Cancelled);
});

test('payouts are capped at the maximum payout', function () {
    $player = $this->api->player(10_000_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'away']], '20000');
    config(['sportsbook.limits.max_payout' => 50_000]);

    recordScore($fixture, 0, 1);

    expect($bet->fresh()->payout)->toBe(5_000_000);
});

test('winnings tax is withheld by the API and kept from its answer; nothing is taxed here', function () {
    $this->api->taxRates['sportsbook'] = 0.2;
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    recordScore($fixture, 1, 0);

    expect($bet->fresh())->payout->toBe(21_000)->tax->toBe(2_200)->netPayout()->toBe(18_800)
        ->and($this->api->callsTo('settle')[0]['body'])->toMatchArray(['payout' => '210.00'])
        ->and($this->api->balanceOf($player))->toBe(108_800);
});

test('a corrected result reverses the settlement and settles version 2', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    recordScore($fixture, 1, 0);

    recordScore($fixture, 1, 1, ['note' => 'Late equaliser missed'], FixtureResult::SOURCE_ADMIN, User::factory()->create());

    expect($bet->fresh())->status->toBe(BetStatus::Lost)->settlement_version->toBe(1)
        ->and($this->api->balanceOf($player))->toBe(90_000)
        ->and(WagerOp::query()->orderBy('id')->get(['operation', 'version', 'status'])->toArray())->toBe([
            ['operation' => 'settle', 'version' => 1, 'status' => 'sent'],
            ['operation' => 'reverse', 'version' => 1, 'status' => 'sent'],
            ['operation' => 'settle', 'version' => 2, 'status' => 'sent'],
        ])
        ->and($this->api->wager('sportsbook', $bet->wager_reference))->toMatchArray(['status' => 'lost', 'version' => 2]);
    expect(Activity::query()->where('event', 'result-corrected')->sole()->properties->get('previous_result'))->toBe('1-0');
});

test('a correction can take the balance below zero at the API', function () {
    $player = $this->api->player(10_000);
    $fixture = pricedFixture();
    betOn($player, [[$fixture, 'home']]);
    recordScore($fixture, 1, 0);
    $this->api->setBalance($player, 0);

    recordScore($fixture, 0, 1);

    expect($this->api->balanceOf($player))->toBe(-21_000);
});

test('correcting a result twice to the same score pays only once', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    betOn($player, [[$fixture, 'home']]);

    recordScore($fixture, 1, 0);
    recordScore($fixture, 1, 0);
    recordScore($fixture, 1, 0);

    expect($this->api->balanceOf($player))->toBe(111_000);
});

test('voiding a leg on a settled bet re-settles it at 1.00 and survives a later correction', function () {
    $player = $this->api->player(100_000);
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($player, [[$first, 'home'], [$second, 'home']]);
    recordScore($first, 1, 0);
    recordScore($second, 0, 1);

    app(VoidLeg::class)->handle($bet->legs()->where('fixture_id', $second->id)->sole(), 'Priced in error', User::factory()->create());

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(21_000)
        ->and($this->api->balanceOf($player))->toBe(111_000);

    recordScore($second, 0, 2);

    expect($bet->fresh()->payout)->toBe(21_000)
        ->and($this->api->balanceOf($player))->toBe(111_000)
        ->and(Activity::query()->where('event', 'leg-voided')->exists())->toBeTrue();
});

test('cancelling an open bet returns the stake through the API; a settled bet cannot be cancelled', function () {
    $player = $this->api->player(100_000);
    $bet = betOn($player, [[pricedFixture(), 'home']]);
    $admin = User::factory()->create();

    app(CancelBet::class)->handle($bet, $admin, 'Placed in error');

    expect($bet->fresh())->status->toBe(BetStatus::Cancelled)
        ->and($this->api->callsTo('settle')[0]['body'])->toMatchArray(['outcome' => 'void', 'reason' => 'Cancelled: Placed in error'])
        ->and($this->api->balanceOf($player))->toBe(100_000);

    $settled = betOn($player, [[pricedFixture(), 'home']]);
    $settled->update(['status' => BetStatus::Lost]);

    expect(fn () => app(CancelBet::class)->handle($settled, $admin, 'Too late'))->toThrow(ValidationException::class);
});

test('a leg on a match moved more than 48 hours is voided; a smaller move is not', function () {
    $moved = pricedFixture();
    $nudged = pricedFixture();
    $bet = betOn($this->api->player(), [[$moved, 'home'], [$nudged, 'home']]);

    $moved->update(['kicks_off_at' => $moved->kicks_off_at->addHours(49)]);
    $nudged->update(['kicks_off_at' => $nudged->kicks_off_at->addHours(3)]);

    expect(app(VoidPostponedLegs::class)->handle())->toBe(1);
    expect($bet->fresh()->legs->pluck('status')->all())->toBe([BetLegStatus::Void, BetLegStatus::Open]);
});

test('a free bet win pays only the winnings, never the voucher', function () {
    $player = $this->api->player(0);
    $fixture = pricedFixture();
    $freeBet = FreeBet::factory()->for($player)->create(['amount' => 10_000]);
    $bet = betOn($player, [[$fixture, 'home']], freeBetId: $freeBet->id);

    recordScore($fixture, 1, 0);

    expect($this->api->callsTo('settle')[0]['body'])->toMatchArray(['outcome' => 'won', 'payout' => '110.00'])
        ->and($bet->fresh())->payout->toBe(11_000)->bonus->toBe(0)
        ->and($this->api->balanceOf($player))->toBe(11_000);
});

test('a void free bet gets a replacement voucher, taken back if a correction reopens it', function () {
    $player = $this->api->player(0);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']], freeBetId: FreeBet::factory()->for($player)->create()->id);

    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    $replacement = FreeBet::query()->where('replaces_bet_id', $bet->id)->sole();
    expect($replacement)->source->toBe(FreeBetSource::Replacement)->status->toBe(FreeBetStatus::Available)
        ->and($this->api->balanceOf($player))->toBe(0);

    recordScore($fixture, 1, 0);

    expect($replacement->fresh()->status)->toBe(FreeBetStatus::Void)
        ->and($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($this->api->balanceOf($player))->toBe(11_000);
});

test('a bet still being checked is not settled; once found it settles on the results already in', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $this->api->next('place', 'lost-answer');
    $bet = betOn($player, [[$fixture, 'home']]);

    recordScore($fixture, 1, 0);

    expect($bet->fresh()->status)->toBe(BetStatus::Pending)
        ->and(WagerOp::count())->toBe(0);

    (new ReconcileWager('bet', $bet->id))->handle(app(WagerPlacement::class));

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($this->api->balanceOf($player))->toBe(111_000);
});

test('an op the API cannot take yet is tried again later, and the ops after it wait their turn', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    $this->api->next('settle', [409, 'version_out_of_order']);

    recordScore($fixture, 1, 0);
    recordScore($fixture, 0, 1);

    expect(WagerOp::query()->orderBy('id')->pluck('status')->all())->toBe(['pending', 'pending', 'pending'])
        ->and(WagerOp::query()->orderBy('id')->first())->attempts->toBe(1)->last_error->toBe('version_out_of_order')
        ->and($this->api->callsTo('reverse'))->toBe([]);

    $this->travel(1)->minutes();
    $this->artisan('sportsbook:send-wager-ops')->assertSuccessful();

    expect(WagerOp::query()->pluck('status')->unique()->all())->toBe(['sent'])
        ->and($this->api->wager('sportsbook', $bet->wager_reference))->toMatchArray(['status' => 'lost', 'version' => 2])
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('a refused op waits for an operator, holds back the next one, and can be put back in line', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    $this->api->next('settle', [422, 'payout_above_limit']);

    recordScore($fixture, 1, 0);
    recordScore($fixture, 0, 1);

    $first = WagerOp::query()->orderBy('id')->first();
    expect($first)->status->toBe(WagerOp::FAILED)->last_error->toBe('payout_above_limit')
        ->and($this->api->callsTo('reverse'))->toBe([]);

    $this->artisan('sportsbook:wager-ops')->expectsOutputToContain('payout_above_limit')->assertSuccessful();
    $this->artisan('sportsbook:wager-ops', ['--retry' => $first->id])->assertSuccessful();

    expect(WagerOp::query()->pluck('status')->unique()->all())->toBe(['sent'])
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('an op that never gets an answer goes to the operator after its last attempt', function () {
    config(['sportsbook.wagers.op_max_attempts' => 2]);
    $fixture = pricedFixture();
    betOn($this->api->player(), [[$fixture, 'home']]);
    $this->api->next('settle', 'timeout', 'timeout');

    recordScore($fixture, 1, 0);
    $this->travel(1)->minutes();
    app(WagerOps::class)->sendDue();

    expect(WagerOp::sole())->status->toBe(WagerOp::FAILED)->attempts->toBe(2)->last_status->toBeNull();
});

test('ops are written with the decision: a rollback leaves neither', function () {
    $fixture = pricedFixture();
    $bet = betOn($this->api->player(), [[$fixture, 'home']]);

    try {
        DB::transaction(function () use ($fixture) {
            recordScore($fixture, 1, 0);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($bet->fresh()->status)->toBe(BetStatus::Open)
        ->and(WagerOp::count())->toBe(0)
        ->and($this->api->callsTo('settle'))->toBe([]);
});

test('bets cannot be placed on a match that was called off', function () {
    $fixture = pricedFixture();
    $fixture->update(['status' => FixtureStatus::Cancelled]);

    expect(fn () => betOn($this->api->player(), [[$fixture, 'home']]))->toThrow(BetNotPlaced::class);
});
