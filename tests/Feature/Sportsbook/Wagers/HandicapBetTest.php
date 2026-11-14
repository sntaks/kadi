<?php

use App\Livewire\Sports\BetSlip;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use Livewire\Livewire;

/*
 * Plan §10.5 phase 1: a handicap bet from the slip to settlement, through the fake wager API.
 */

beforeEach(function () {
    config(['platform.sports' => true]);
    $this->api = wagerApi();
});

function handicapSelection(Fixture $fixture, float $line, string $outcome): Selection
{
    $market = Market::factory()->for($fixture)->create([
        'type' => MarketType::Spreads, 'line' => $line, 'key' => Market::keyFor(MarketType::Spreads, $line), 'status' => MarketStatus::Open,
    ]);
    Selection::factory()->for($market)->create(['outcome' => 'home', 'name' => $fixture->home_team.' '.Market::formatLine($line, signed: true), 'feed_price' => '2.06']);
    Selection::factory()->for($market)->create(['outcome' => 'away', 'name' => $fixture->away_team.' '.Market::formatLine(-$line, signed: true), 'feed_price' => '1.73']);

    return $market->selections()->where('outcome', $outcome)->sole();
}

test('the slip names a handicap pick by its side and the market by the home line', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3), 'home_team' => 'Manchester City', 'away_team' => 'Fulham']);
    $pick = handicapSelection($fixture, -1.5, 'away');

    Livewire::test(BetSlip::class)->call('syncPicks', [slipPick($pick)])
        ->assertSeeHtml('<b>Fulham +1.5</b>')
        ->assertSee('Handicap -1.5 · ', escape: false);
});

test('a handicap bet records its line and settles on the full-time score', function (int $home, int $away, BetStatus $status, BetLegStatus $leg) {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $pick = handicapSelection($fixture, -1.0, 'away');

    $bet = app(PlaceBet::class)->handle($player, [slipPick($pick)], '100');

    expect($bet->legs->sole())
        ->market_type->toBe(MarketType::Spreads)
        ->market_line->toBe('-1.00')
        ->market_name->toBe('Handicap -1');

    recordScore($fixture, $home, $away);

    expect($bet->fresh()->status)->toBe($status)
        ->and($bet->fresh()->legs->sole()->status)->toBe($leg);
})->with([
    'away +1 losing by none wins' => [1, 1, BetStatus::Won, BetLegStatus::Won],
    'away +1 losing by one is a push' => [2, 1, BetStatus::Void, BetLegStatus::Void],
    'away +1 losing by two loses' => [3, 1, BetStatus::Lost, BetLegStatus::Lost],
]);
