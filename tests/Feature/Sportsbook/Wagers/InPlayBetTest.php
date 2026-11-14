<?php

use App\Livewire\Sports\BetSlip;
use App\Sportsbook\Betting\BetNotPlaced;
use App\Sportsbook\Betting\BetSlipQuote;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FreeBetSource;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\OddsChangePolicy;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Jobs\AcceptInPlayBet;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Plan §5 phase 5b: placing bets with a live pick. Held for the delay, checked again, then placed on sportsbook_live.
 */

beforeEach(function () {
    config(['platform.sports' => true, 'platform.sports_in_play' => true]);
    $this->api = wagerApi();
    $this->travelTo(Date::parse('2026-11-21 15:30:00', 'UTC'));

    $football = Sport::firstWhere('slug', 'football') ?? Sport::factory()->configured('football')->create();
    $epl = Competition::factory()->synced()->for($football)->create(['odds_api_key' => 'soccer_epl']);
    // 30 minutes in, Arsenal 1-0 up.
    $this->live = Fixture::factory()->for($epl)->create([
        'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'kicks_off_at' => now('UTC')->subMinutes(30), 'home_score' => 1, 'away_score' => 0,
    ]);
    $this->market = Market::factory()->for($this->live)->create(['source' => MarketSource::Feed, 'status' => MarketStatus::Open, 'priced_at' => now('UTC')]);
    $this->home = Selection::factory()->for($this->market)->create(['outcome' => 'home', 'name' => 'Arsenal', 'feed_price' => '1.50']);
    Selection::factory()->for($this->market)->create(['outcome' => 'draw', 'name' => 'Draw', 'feed_price' => '3.90']);
    Selection::factory()->for($this->market)->create(['outcome' => 'away', 'name' => 'Chelsea', 'feed_price' => '6.50']);
    $this->player = $this->api->player(1_000_000);
});

/** Place a slip with the live home pick (and any extra picks), as the bet slip does. */
function placeLive(array $extra = [], string $stake = '100', ?OddsChangePolicy $policy = null, ?int $freeBetId = null): Bet
{
    return app(PlaceBet::class)->handle(test()->player, [slipPick(test()->home), ...$extra], $stake, $policy, freeBetId: $freeBetId);
}

/** The delay is over: run the job that checks and sends the bet. */
function acceptAfterDelay(Bet $bet): Bet
{
    test()->travelTo(now()->addSeconds(9));
    runAcceptJob($bet);

    return $bet->fresh(['legs']);
}

/** Runs the job itself: Queue::fake() would also catch dispatchSync(). */
function runAcceptJob(Bet $bet): void
{
    app()->call([new AcceptInPlayBet($bet->id), 'handle']);
}

test('a live pick is held as pending on sportsbook_live with its score, and nothing is sent yet', function () {
    Queue::fake([AcceptInPlayBet::class]);

    $bet = placeLive();

    expect($bet)->status->toBe(BetStatus::Pending)->product->toBe('sportsbook_live')
        ->and($bet->accept_after->equalTo(now()->addSeconds(8)))->toBeTrue()
        ->and($bet->legs->sole())->in_play->toBeTrue()->score_at_placement->toBe('1-0')
        ->and($this->api->callsTo('place'))->toBe([]);
    Queue::assertPushed(AcceptInPlayBet::class, fn (AcceptInPlayBet $job): bool => $job->betId === $bet->id && $job->delay->equalTo($bet->accept_after));
});

test('after the delay it is placed on sportsbook_live, and settles on it too', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $bet = acceptAfterDelay(placeLive());

    expect($bet->status)->toBe(BetStatus::Open)
        ->and($this->api->callsTo('place')[0]['body']['product'])->toBe('sportsbook_live')
        ->and($this->api->wager('sportsbook_live', $bet->wager_reference))->not->toBeNull();

    recordScore($this->live, 2, 0);

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($this->api->callsTo('settle')[0]['body']['product'])->toBe('sportsbook_live');
});

test('a goal during the delay rejects it without charging, and gives a free bet back', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $freeBet = FreeBet::factory()->for($this->player)->create(['amount' => 10_000, 'min_odds' => '1.20', 'source' => FreeBetSource::Admin]);
    $bet = placeLive(freeBetId: $freeBet->id);
    $this->live->update(['home_score' => 2]);

    $bet = acceptAfterDelay($bet);

    expect($bet)->status->toBe(BetStatus::Rejected)->reject_reason->toBe('live_score_changed')
        ->and($this->api->callsTo('place'))->toBe([])
        ->and($freeBet->fresh()->status)->toBe(FreeBetStatus::Available);
});

test('a price that moved down is refused under "higher", and taken at the new price under "any"', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $higher = placeLive(policy: OddsChangePolicy::Higher);
    $any = placeLive(stake: '200', policy: OddsChangePolicy::Any);
    $this->home->update(['feed_price' => '1.40']);
    $this->market->update(['priced_at' => now('UTC')->addSeconds(9)]);

    expect(acceptAfterDelay($higher))->status->toBe(BetStatus::Rejected)->reject_reason->toBe('live_price_changed');

    runAcceptJob($any);
    $any = $any->fresh(['legs']);

    expect($any->status)->toBe(BetStatus::Open)
        ->and($any->legs->sole()->price)->toBe('1.40')
        ->and($any->total_odds)->toBe('1.40')
        ->and($any->potential_payout)->toBe(28_000);
});

test('a price that moved up keeps the price the player asked for', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $bet = placeLive();
    $this->home->update(['feed_price' => '1.70']);
    $this->market->update(['priced_at' => now('UTC')->addSeconds(9)]);

    $bet = acceptAfterDelay($bet);

    expect($bet->status)->toBe(BetStatus::Open)->and($bet->legs->sole()->price)->toBe('1.50');
});

test('a market suspended during the delay, or live prices gone stale, reject the bet', function (Closure $change, string $code) {
    Queue::fake([AcceptInPlayBet::class]);
    $bet = placeLive();
    $change($this->market);

    expect(acceptAfterDelay($bet))->status->toBe(BetStatus::Rejected)->reject_reason->toBe($code)
        ->and($this->api->callsTo('place'))->toBe([]);
})->with([
    'suspended for a goal' => [fn (Market $market) => $market->suspend(SuspensionReason::ScoreChanged), 'live_market_closed'],
    'prices stale' => [fn (Market $market) => $market->update(['priced_at' => now('UTC')->subSeconds(120)]), 'live_price_stale'],
]);

test('a player token the API refuses (signed out during the delay) rejects the bet without renewing it', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $bet = placeLive();
    $redeems = count($this->api->callsTo('redeem'));
    $this->api->next('place', [401, 'invalid_player_token']);

    $bet = acceptAfterDelay($bet);

    expect($bet)->status->toBe(BetStatus::Rejected)->reject_reason->toBe('player_token_invalid')
        ->and(count($this->api->callsTo('redeem')))->toBe($redeems);
});

test('a slip with a live pick has the in-play limits: KES 5,000 stake, KES 250,000 payout', function () {
    $quote = BetSlipQuote::for([slipPick($this->home)], '6000', app(BettableSelections::class));

    expect($quote->inPlay)->toBeTrue()
        ->and($quote->stakeErrors)->toBe(['The maximum stake on a bet with a live pick is KES 5,000.']);

    $this->home->update(['feed_price' => '900.00']);
    $capped = BetSlipQuote::for([slipPick($this->home->fresh())], '5000', app(BettableSelections::class));

    expect($capped->possibleWin)->toBe(250_000.0)->and($capped->isPayoutCapped)->toBeTrue();
    expect(fn () => placeLive(stake: '6000'))->toThrow(BetNotPlaced::class, 'The maximum stake on a bet with a live pick is KES 5,000.');
});

test('a live win is capped at the in-play maximum payout at settlement', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $this->home->update(['feed_price' => '80.00']);
    $bet = acceptAfterDelay(placeLive(stake: '5000'));

    recordScore($this->live, 3, 0);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(25_000_000);
});

test('a pre-match slip is placed at once, on sportsbook, as before', function () {
    $bet = betOn($this->player, [[pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);

    expect($bet)->status->toBe(BetStatus::Open)->product->toBeNull()->accept_after->toBeNull()
        ->and($bet->legs->sole()->in_play)->toBeFalse()
        ->and($this->api->callsTo('place')[0]['body']['product'])->toBe('sportsbook');
});

test('with in play off, or past the late phase, a match that has started takes no bets', function () {
    config(['platform.sports_in_play' => false]);
    expect(app(BettableSelections::class)->unavailableReason($this->home))->toBe('This match has started.');

    config(['platform.sports_in_play' => true]);
    $this->travelTo(now()->addMinutes(56));
    expect(app(BettableSelections::class)->unavailableReason($this->home->fresh()))->toBe('This match has started.');
});

test('the reconcile sweep leaves a held live bet to its own job', function () {
    Queue::fake([AcceptInPlayBet::class, ReconcileWager::class]);
    $bet = placeLive();

    $this->travelTo(now()->addSeconds(31));
    $this->artisan('sportsbook:reconcile-wagers')->assertSuccessful();
    Queue::assertNotPushed(ReconcileWager::class);

    // Its job never ran, well past the delay: looked up like any unanswered placement.
    $this->travelTo($bet->accept_after->addSeconds(31));
    $this->artisan('sportsbook:reconcile-wagers')->assertSuccessful();
    Queue::assertPushed(ReconcileWager::class, fn (ReconcileWager $job): bool => $job->subjectId === $bet->id);
});

test('the slip says a live bet is being checked, and why it was refused', function () {
    Queue::fake([AcceptInPlayBet::class]);
    $slip = Livewire::actingAs($this->player)->test(BetSlip::class)->call('syncPicks', [slipPick($this->home)])->set('stake', '100')->call('placeBet');

    $slip->assertSeeHtml('data-test="live-delay"');

    $this->live->update(['home_score' => 2]);
    acceptAfterDelay(Bet::sole());

    $slip->call('refreshPlaced')->assertSee('The score changed while your live bet was being checked. Nothing was charged.');
});
