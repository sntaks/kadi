<?php

use App\Sportsbook\Betting\BetNotPlaced;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\BetType;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\OddsChangePolicy;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Fixture;
use Illuminate\Support\Facades\Cache;

/*
 * Ported from the standalone sportsbook: the slip rules are unchanged; the stake is now a Kadi API wager.
 */

/**
 * The reason PlaceBet refused, or null if it placed the bet.
 */
function refusal(Closure $place): ?string
{
    try {
        $place();
    } catch (BetNotPlaced $e) {
        return $e->reason;
    }

    return null;
}

beforeEach(function () {
    $this->api = wagerApi();
});

test('places a single at the current price and takes the stake from the Kadi wallet', function () {
    $player = $this->api->player(100_000);
    $home = selectionFor(pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']), 'home');

    $bet = app(PlaceBet::class)->handle($player, [slipPick($home)], '100');

    expect($bet)
        ->type->toBe(BetType::Single)
        ->status->toBe(BetStatus::Open)
        ->stake->toBe(10_000)
        ->total_odds->toBe('2.10')
        ->potential_payout->toBe(21_000)
        ->reference->toMatch('/^[A-HJ-NP-Z2-9]{8}$/')
        ->wager_reference->toBeUuid()
        ->and($this->api->balanceOf($player))->toBe(90_000)
        ->and($this->api->wager('sportsbook', $bet->wager_reference))->toMatchArray(['status' => 'open', 'stake' => 10_000, 'funding' => 'cash']);
    expect($bet->legs->sole())
        ->selection_id->toBe($home->id)
        ->fixture_name->toBe('Arsenal v Chelsea')
        ->market_name->toBe('1X2')
        ->selection_name->toBe('Arsenal')
        ->price->toBe('2.10')
        ->status->toBe(BetLegStatus::Open);
});

test('the balance the API returns is shown at once', function () {
    $player = $this->api->player(100_000);

    betOn($player, [[pricedFixture(), 'home']]);

    expect(Cache::get("wallet_balance_{$player->id}"))->toBe(900.0)
        ->and(Cache::get("wallet_balance_version_{$player->id}"))->toBe($this->api->versions[$player->linked_id]);
});

test('several picks make one multi at the combined odds', function () {
    $picks = [
        slipPick(selectionFor(pricedFixture(), 'home')),
        slipPick(selectionFor(pricedFixture(), 'draw')),
        slipPick(selectionFor(pricedFixture(), 'away')),
    ];

    $bet = app(PlaceBet::class)->handle($this->api->player(), $picks, '100');

    expect($bet)
        ->type->toBe(BetType::Multi)
        ->total_odds->toBe('24.99')
        ->bonus_percent->toBe(3)
        // KES 2,499 plus the 3% multibet bonus on the KES 2,399 profit.
        ->potential_payout->toBe(257_097)
        ->and($bet->legs)->toHaveCount(3);
});

test('the possible win is capped at the maximum payout', function () {
    config(['sportsbook.limits.max_payout' => 1_000]);
    $bet = app(PlaceBet::class)->handle($this->api->player(1_000_000), [slipPick(selectionFor(pricedFixture(), 'home'))], '1000');

    expect($bet->potential_payout)->toBe(100_000);
});

test('the odds-change setting decides whether a moved price stops the bet', function (OddsChangePolicy $policy, string $seen, ?string $expected) {
    $home = selectionFor(pricedFixture(), 'home');

    expect(refusal(fn () => app(PlaceBet::class)->handle($this->api->player(), [slipPick($home, $seen)], '100', $policy)))->toBe($expected);
})->with([
    'any, price fell' => [OddsChangePolicy::Any, '2.50', null],
    'higher only, price rose' => [OddsChangePolicy::Higher, '1.90', null],
    'higher only, price fell' => [OddsChangePolicy::Higher, '2.50', BetNotPlaced::PRICE_CHANGED],
    'none, price rose' => [OddsChangePolicy::None, '1.90', BetNotPlaced::PRICE_CHANGED],
    'none, unchanged' => [OddsChangePolicy::None, '2.10', null],
]);

test('a bet accepted after a price change is placed at the new price', function () {
    $bet = app(PlaceBet::class)->handle($this->api->player(), [slipPick(selectionFor(pricedFixture(), 'home'), '1.90')], '100', OddsChangePolicy::Higher);

    expect($bet->legs->sole()->price)->toBe('2.10');
});

test('the player\'s saved odds-change setting is used when none is given', function () {
    $player = $this->api->player(100_000, ['odds_change_policy' => 'none']);

    expect(refusal(fn () => app(PlaceBet::class)->handle($player, [slipPick(selectionFor(pricedFixture(), 'home'), '1.90')], '100')))->toBe(BetNotPlaced::PRICE_CHANGED);
});

test('a pick that can no longer be bet on stops the bet before any money moves', function (Closure $close) {
    $fixture = pricedFixture();
    $close($fixture);
    $player = $this->api->player();

    expect(refusal(fn () => app(PlaceBet::class)->handle($player, [slipPick(selectionFor($fixture->fresh(['matchResultMarket.selections', 'matchResultMarket.fixture']), 'home'))], '100')))
        ->toBe(BetNotPlaced::UNAVAILABLE);
    expect(Bet::count())->toBe(0)
        ->and($this->api->callsTo('place'))->toBe([]);
})->with([
    'kicked off' => [fn (Fixture $fixture) => $fixture->update(['kicks_off_at' => now('UTC')->subMinute()])],
    'suspended' => [fn (Fixture $fixture) => $fixture->matchResultMarket->update(['status' => MarketStatus::Suspended])],
]);

test('two picks from the same match are refused', function () {
    $fixture = pricedFixture();

    expect(refusal(fn () => app(PlaceBet::class)->handle($this->api->player(), [slipPick(selectionFor($fixture, 'home')), slipPick(selectionFor($fixture, 'away'))], '100')))
        ->toBe(BetNotPlaced::DUPLICATE_MATCH);
});

test('the stake must be whole shillings within the stake limits', function (string $stake) {
    expect(refusal(fn () => app(PlaceBet::class)->handle($this->api->player(10_000_000), [slipPick(selectionFor(pricedFixture(), 'home'))], $stake)))
        ->toBe(BetNotPlaced::STAKE);
})->with(['below the minimum' => '9', 'above the maximum' => '20001', 'cents' => '100.50', 'not a number' => 'ten']);

test('submitting the same slip twice places one bet', function () {
    $player = $this->api->player(100_000);
    $picks = [slipPick(selectionFor(pricedFixture(), 'home'))];

    $first = app(PlaceBet::class)->handle($player, $picks, '100', clientKey: 'slip-1');
    $second = app(PlaceBet::class)->handle($player, $picks, '100', clientKey: 'slip-1');

    expect($second->is($first))->toBeTrue()
        ->and(Bet::count())->toBe(1)
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('an empty slip is refused', function () {
    expect(refusal(fn () => app(PlaceBet::class)->handle($this->api->player(), [], '100')))->toBe(BetNotPlaced::EMPTY_SLIP);
});

test('nothing is placed while sports betting is switched off', function () {
    config(['platform.sports_betting' => false]);

    expect(refusal(fn () => betOn($this->api->player(), [[pricedFixture(), 'home']])))->toBe(BetNotPlaced::WALLET)
        ->and($this->api->calls)->toBe([]);
});

test('a player who has not verified their e-mail cannot bet', function () {
    $player = $this->api->player(100_000, ['email_verified_at' => null]);

    expect(refusal(fn () => betOn($player, [[pricedFixture(), 'home']])))->toBe(BetNotPlaced::NOT_ALLOWED);
});
