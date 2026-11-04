<?php

use App\Sportsbook\Betting\BetSlipQuote;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Betting\Jackpot\VoidPostponedJackpotMatches;
use App\Sportsbook\Betting\Payout;
use App\Sportsbook\Betting\Settlement\FetchResults;
use App\Sportsbook\Betting\Settlement\RecordResult;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\Market;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Pricing\Derived\DeriveMarkets;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/*
 * Phase 7 review: the merge's timezone handling, and fixes to the ported code before money depends on it.
 */

test('a jackpot closes at its first kick-off, not three hours earlier', function () {
    $jackpot = Jackpot::factory()->open()->withMatches(1)->create();
    $jackpot->jackpotFixtures()->first()->fixture->update(['kicks_off_at' => Date::parse('2026-11-05 15:00:00', 'UTC')]);

    expect($jackpot->closesAt()->utc()->format('Y-m-d H:i'))->toBe('2026-11-05 15:00');

    $this->travelTo(Date::parse('2026-11-05 14:00:00', 'UTC'));
    expect($jackpot->fresh()->isTakingEntries())->toBeTrue();
});

test('derived markets are suspended with a suspended 1X2, not re-priced from its old prices', function () {
    $fixture = pricedFixture([], ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']);
    $fixture->matchResultMarket->selections->each(fn ($selection, $i) => $selection->update(['fair_probability' => [0.45, 0.28, 0.27][$i]]));
    $fixture->matchResultMarket->update(['priced_at' => now('UTC'), 'status' => MarketStatus::Open]);

    app(DeriveMarkets::class)->handle($fixture->fresh());
    $derived = Market::query()->where('fixture_id', $fixture->id)->where('source', MarketSource::Derived);
    expect($derived->clone()->where('status', MarketStatus::Open)->count())->toBeGreaterThan(0);

    $fixture->matchResultMarket->update(['status' => MarketStatus::Suspended, 'suspension_reason' => SuspensionReason::InsufficientBookmakers]);
    app(DeriveMarkets::class)->handle($fixture->fresh());

    expect($derived->clone()->where('status', MarketStatus::Open)->count())->toBe(0)
        ->and($derived->clone()->where('type', '!=', MarketType::MatchResult)->pluck('suspension_reason')->unique()->all())
        ->toBe([SuspensionReason::InsufficientBookmakers]);
});

test('the slip shows exactly what the bet would pay', function (string $stake, string $price) {
    $fixture = pricedFixture([], ['home' => $price, 'draw' => '3.40', 'away' => '3.50']);

    $quote = BetSlipQuote::for([slipPick(selectionFor($fixture, 'home'))], $stake, app(BettableSelections::class));

    expect((int) round($quote->possibleWin * 100))->toBe(Payout::for((int) $stake * 100, $price));
})->with([['10', '2.01'], ['33', '1.15'], ['7', '3.33']]);

test('a failed scores call is asked again next hour, not in 12 hours', function () {
    $jackpot = Jackpot::factory()->open()->withMatches(1)->create();
    $fixture = $jackpot->jackpotFixtures()->first()->fixture;
    $fixture->competition->update(['auto_settle' => true]);
    $fixture->update(['kicks_off_at' => now('UTC')->subHours(4)]);

    $api = Mockery::mock(OddsApi::class);
    $api->shouldReceive('scores')->andThrow(new ConnectionException('timed out'));
    app()->instance(OddsApi::class, $api);

    app(FetchResults::class)->handle();

    expect($fixture->competition->fresh()->scores_checked_at)->toBeNull();
});

test('one game that cannot be recorded does not throw away the rest of a paid response', function () {
    $jackpot = Jackpot::factory()->open()->withMatches(2)->create();
    $fixtures = $jackpot->jackpotFixtures()->with('fixture')->get()->pluck('fixture');
    $fixtures->each->update(['kicks_off_at' => now('UTC')->subHours(4)]);

    $games = $fixtures->map(fn (Fixture $fixture) => [
        'id' => $fixture->external_id, 'completed' => true, 'home_team' => $fixture->home_team, 'away_team' => $fixture->away_team,
        'scores' => [['name' => $fixture->home_team, 'score' => '1'], ['name' => $fixture->away_team, 'score' => '0']],
    ])->all();
    $api = Mockery::mock(OddsApi::class);
    $api->shouldReceive('scores')->andReturn($games);
    app()->instance(OddsApi::class, $api);

    $record = Mockery::mock(RecordResult::class);
    $record->shouldReceive('handle')->once()->ordered()->andThrow(new RuntimeException('database hiccup'));
    $record->shouldReceive('handle')->once()->ordered()->andReturn(['legs' => 0, 'bets' => 0]);
    app()->instance(RecordResult::class, $record);

    $counts = app(FetchResults::class)->handle();

    expect($counts)->toBe(['competitions' => 1, 'results' => 1])
        ->and($fixtures->first()->competition->fresh()->scores_checked_at)->not->toBeNull();
});

test('a postponed jackpot match stays open when settling the jackpot fails', function () {
    $jackpot = Jackpot::factory()->open()->withMatches(1)->create();
    $match = $jackpot->jackpotFixtures()->first();
    $match->fixture->update(['kicks_off_at' => $match->kicks_off_at->addDays(5)]);

    $wallet = Mockery::mock(SportsWallet::class);
    $wallet->shouldReceive('settleJackpot')->andThrow(new RuntimeException('wallet down'));
    app()->instance(SportsWallet::class, $wallet);

    expect(app(VoidPostponedJackpotMatches::class)->handle())->toBe(0)
        ->and($match->fresh()->is_void)->toBeFalse()
        ->and($match->fresh()->void_reason)->toBeNull();
});

test('paid Odds API calls run one at a time inside the budget', function () {
    $budget = new CreditBudget(monthlyCap: 500, reserve: 50);

    expect($budget->spend(1, fn () => Cache::lock(CreditBudget::LOCK, 1)->get()))->toBeFalse() // held while the call runs
        ->and(Cache::lock(CreditBudget::LOCK, 1)->get())->toBeTrue(); // and released after
});

test('a competition that left the feed is not listed to players', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    expect(app(FixtureListing::class)->forTab('highlights', 'h2h')->pluck('id'))->toContain($fixture->id);

    Competition::query()->whereKey($fixture->competition_id)->update(['is_active_in_feed' => false]);

    expect(app(FixtureListing::class)->forTab('highlights', 'h2h')->pluck('id'))->not->toContain($fixture->id);
});
