<?php

use App\Jobs\RevokeGameSessions;
use App\Models\User;
use App\Sportsbook\Betting\BetNotPlaced;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Auth\Events\Logout;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/*
 * Phase 8 (plan §5.3): a bet is saved as pending, then placed with POST /wagers. An unclear outcome is looked up,
 * never guessed, and a bet either debits once or not at all.
 */

beforeEach(function () {
    $this->api = wagerApi();
});

function placeFor(User $player, string $stake = '100', ?string $clientKey = null): Bet
{
    return app(PlaceBet::class)->handle($player, [slipPick(selectionFor(pricedFixture(), 'home'))], $stake, clientKey: $clientKey);
}

test('the player token comes from a launch token redeemed with the game key, and is reused', function () {
    $player = $this->api->player();

    placeFor($player);
    placeFor($player);

    expect($this->api->callsTo('launch'))->toHaveCount(1)
        ->and($this->api->callsTo('launch')[0]['body'])->toMatchArray(['game' => 'sportsbook'])
        ->and($this->api->callsTo('redeem'))->toHaveCount(1)
        ->and($this->api->callsTo('redeem')[0]['headers']['X-API-KEY'][0])->toBe('sportsbook-game-key-for-tests')
        ->and($this->api->callsTo('place'))->toHaveCount(2)
        ->and($this->api->callsTo('place')[0]['headers']['Authorization'][0])->toStartWith('Bearer pt|');
});

test('the wager is sent with its UUID, the product and the stake in shillings', function () {
    $bet = placeFor($this->api->player(), '150');

    expect($this->api->callsTo('place')[0]['body'])->toBe([
        'product' => 'sportsbook', 'reference' => $bet->wager_reference, 'funding' => 'cash', 'stake' => '150.00', 'channel' => 'web',
    ]);
});

test('too little money: the bet is kept as not placed, nothing is charged, and the slip can be sent again', function () {
    $player = $this->api->player(5_000);

    expect(fn () => placeFor($player, clientKey: 'slip-1'))->toThrow(BetNotPlaced::class, 'Your balance is too low');

    expect(Bet::sole())->status->toBe(BetStatus::Rejected)->reject_reason->toBe('insufficient_funds')->client_key->toBeNull()
        ->and($this->api->balanceOf($player))->toBe(5_000);

    $this->api->setBalance($player, 50_000);

    expect(placeFor($player, clientKey: 'slip-1')->status)->toBe(BetStatus::Open);
});

test('a player on a break cannot bet: refused here when the break is known', function () {
    $player = $this->api->player();
    $this->api->breaks[$player->linked_id] = now()->addDays(3)->toIso8601String();

    expect(fn () => placeFor($player))->toThrow(BetNotPlaced::class, 'Betting is paused');

    expect(Bet::count())->toBe(0)
        ->and($this->api->callsTo('place'))->toBe([]);
});

test('a player on a break cannot bet: the API refuses a break that started since', function () {
    $player = $this->api->player();
    placeFor($player);
    $this->api->breaks[$player->linked_id] = now()->addDays(3)->toIso8601String();

    try {
        placeFor($player);
    } catch (BetNotPlaced $e) {
        expect($e->reason)->toBe(BetNotPlaced::NOT_ALLOWED);
    }

    expect(Bet::query()->latest('id')->first())->status->toBe(BetStatus::Rejected)->reject_reason->toBe('player_excluded')
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('no answer: the bet waits as pending, then the lookup finds it placed and it opens', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', 'lost-answer');

    $bet = placeFor($player);

    expect($bet->status)->toBe(BetStatus::Pending)
        ->and($this->api->balanceOf($player))->toBe(90_000);
    Queue::assertPushed(ReconcileWager::class, fn (ReconcileWager $job) => $job->subjectType === 'bet' && $job->subjectId === $bet->id);

    (new ReconcileWager('bet', $bet->id))->handle(app(WagerPlacement::class));

    expect($bet->fresh()->status)->toBe(BetStatus::Open)
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('no answer and never placed: a void tombstone is sent and the bet is not placed; a late placement cannot land', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', 'timeout');

    $bet = placeFor($player);
    (new ReconcileWager('bet', $bet->id))->handle(app(WagerPlacement::class));

    expect($bet->fresh())->status->toBe(BetStatus::Rejected)->reject_reason->toBe('not_placed')
        ->and($this->api->wager('sportsbook', $bet->wager_reference))->toMatchArray(['status' => 'void', 'tombstone' => true])
        ->and($this->api->balanceOf($player))->toBe(100_000);

    // The original request arriving late is refused by the tombstone.
    $late = app(WagerPlacement::class);
    $token = $late->token($player);
    $bet->update(['status' => BetStatus::Pending]);
    expect($late->place($player, $bet->fresh(), $token)->status)->toBe(BetStatus::Rejected)
        ->and($this->api->balanceOf($player))->toBe(100_000);
});

test('a 429 or 5xx leaves the bet pending for the lookup; the lookup tries again while the API is down', function (array $answer) {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', $answer);

    $bet = placeFor($player);

    expect($bet->status)->toBe(BetStatus::Pending);

    $this->api->next('lookup', 'timeout');
    (new ReconcileWager('bet', $bet->id))->handle(app(WagerPlacement::class));
    expect($bet->fresh()->status)->toBe(BetStatus::Pending);

    $this->artisan('sportsbook:reconcile-wagers');
    Queue::assertPushed(ReconcileWager::class, 1);

    // Once the first lookup's slot has passed, the sweep sends it again.
    $this->travel(3)->minutes();
    $this->artisan('sportsbook:reconcile-wagers');
    Queue::assertPushed(ReconcileWager::class, 2);
})->with([
    '429' => [[429, 'too_many_requests']],
    '503' => [[503, 'server_error']],
]);

test('re-sending a placement whose answer was lost is a replay: charged once', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', 'lost-answer');
    $bet = placeFor($player);

    $placement = app(WagerPlacement::class);
    $bet = $placement->place($player, $bet, $placement->token($player));

    expect($bet->status)->toBe(BetStatus::Open)
        ->and($this->api->balanceOf($player))->toBe(90_000)
        ->and($this->api->callsTo('place'))->toHaveCount(2);
});

test('an expired player token is renewed once and the same wager sent again', function () {
    $player = $this->api->player();
    placeFor($player);
    $this->api->playerTokens = [];

    $bet = placeFor($player);

    expect($bet->status)->toBe(BetStatus::Open)
        ->and($this->api->callsTo('redeem'))->toHaveCount(2)
        ->and($this->api->balanceOf($player))->toBe(80_000);
});

test('a redeem that times out is never retried, and nothing is saved', function () {
    $player = $this->api->player();
    $this->api->next('redeem', 'timeout');

    expect(fn () => placeFor($player))->toThrow(BetNotPlaced::class);

    expect($this->api->callsTo('redeem'))->toHaveCount(1)
        ->and(Bet::count())->toBe(0);
});

test('a free bet is sent as free-bet funding and nothing leaves the wallet; a refused one gives the voucher back', function () {
    $player = $this->api->player(0);
    $freeBet = FreeBet::factory()->for($player)->create(['amount' => 10_000]);

    $bet = betOn($player, [[pricedFixture(), 'home']], freeBetId: $freeBet->id);

    expect($this->api->callsTo('place')[0]['body'])->toMatchArray(['funding' => 'free_bet', 'free_bet_amount' => '100.00'])
        ->and($this->api->callsTo('place')[0]['body'])->not->toHaveKey('stake')
        ->and($bet->status)->toBe(BetStatus::Open)
        ->and($freeBet->fresh()->status)->toBe(FreeBetStatus::Used)
        ->and($this->api->balanceOf($player))->toBe(0);

    $other = FreeBet::factory()->for($player)->create(['amount' => 10_000]);
    $this->api->next('place', [403, 'product_not_active']);

    expect(fn () => betOn($player, [[pricedFixture(), 'home']], freeBetId: $other->id))->toThrow(BetNotPlaced::class);
    expect($other->fresh()->status)->toBe(FreeBetStatus::Available);
});

test('player tokens never reach the logs, the database or the cache in plain text', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', [503, 'server_error']);

    placeFor($player);

    $token = array_key_first($this->api->playerTokens);
    $cached = Cache::get('sportsbook.player-token.'.$player->id);

    expect($logged)->not->toBeEmpty()
        ->and(implode("\n", $logged))->not->toContain($token)->not->toContain('sportsbook-game-key-for-tests')
        ->and(json_encode(DB::table('sb_bets')->get()))->not->toContain($token)
        ->and($cached)->not->toBe($token)
        ->and(Crypt::decryptString($cached))->toBe($token);
});

test('logging out forgets the sportsbook token and ends the API sessions', function () {
    Queue::fake([RevokeGameSessions::class]);
    $player = $this->api->player();
    placeFor($player);

    event(new Logout('web', $player));
    placeFor($player);

    Queue::assertPushed(RevokeGameSessions::class, fn ($job) => $job->customerId === $player->linked_id);
    expect($this->api->callsTo('redeem'))->toHaveCount(2);
});

test('concurrent slips cannot overspend: the API takes each stake against the balance it holds', function () {
    $player = $this->api->player(15_000);

    placeFor($player);

    expect(fn () => placeFor($player))->toThrow(BetNotPlaced::class);
    expect($this->api->balanceOf($player))->toBe(5_000)
        ->and(Bet::query()->where('status', BetStatus::Open)->count())->toBe(1);
});

test('a lookup only an operator can sort out is put on hold, not asked every minute', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player();
    $this->api->next('place', 'timeout');
    $bet = placeFor($player);
    $this->api->next('settle', [409, 'settlement_conflict']);

    (new ReconcileWager('bet', $bet->id))->handle(app(WagerPlacement::class));

    expect($bet->fresh()->status)->toBe(BetStatus::Pending)
        ->and(WagerPlacement::onHold('bet', $bet->id))->toBeTrue();

    $this->travel(5)->minutes();
    $this->artisan('sportsbook:reconcile-wagers');
    Queue::assertPushed(ReconcileWager::class, 1); // only the first, from placing

    $this->travel(WagerPlacement::HOLD_MINUTES)->minutes();
    $this->artisan('sportsbook:reconcile-wagers');
    Queue::assertPushed(ReconcileWager::class, 2);
});

test('the sweep settles an open bet whose results were all in when its settling was cut short', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    // As if a worker stopped between opening the bet and settling it.
    $bet->legs()->update(['status' => 'won']);

    $this->artisan('sportsbook:reconcile-wagers')->assertSuccessful();

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($this->api->balanceOf($player))->toBe(111_000);
});
