<?php

use App\Models\User;
use App\Sportsbook\Betting\Jackpot\CancelJackpot;
use App\Sportsbook\Betting\Jackpot\EnterJackpot;
use App\Sportsbook\Betting\Jackpot\JackpotEntryRefused;
use App\Sportsbook\Betting\Settlement\RecordResult;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Jobs\SendWagerOps;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
 * Jackpot entries are Kadi API wagers of product sportsbook_jackpot: the entry price on entering, each prize (or
 * loss, or refund) through the outbox when the jackpot settles, and reverse + settle of the next version on a
 * correction.
 */

beforeEach(function () {
    $this->api = wagerApi();
});

/**
 * @param  list<string>  $picks  "1", "X" or "2" per match, in order.
 */
function enterJackpot(User $player, Jackpot $jackpot, array $picks, ?string $clientKey = null): JackpotEntry
{
    $matches = $jackpot->jackpotFixtures()->pluck('id')->values();

    return app(EnterJackpot::class)->handle($player, $jackpot, $matches->take(count($picks))->combine($picks)->all(), $clientKey);
}

/**
 * Full-time scores for the jackpot's matches in order; null voids the match.
 *
 * @param  array<int, array{0: int, 1: int}|null>  $scores
 */
function jackpotResults(Jackpot $jackpot, array $scores): void
{
    foreach ($jackpot->jackpotFixtures()->with('fixture')->get()->values() as $index => $match) {
        if (array_key_exists($index, $scores)) {
            app(RecordResult::class)->handle(
                $match->fixture,
                $scores[$index] === null ? ['is_void' => true] : ['home_score' => $scores[$index][0], 'away_score' => $scores[$index][1]],
                FixtureResult::SOURCE_FEED,
            );
        }
    }
}

test('an entry takes the entry price as a sportsbook_jackpot wager', function () {
    $player = $this->api->player(100_000);

    $entry = enterJackpot($player, openJackpot(), ['1', 'X', '2']);

    expect($entry)->status->toBe(JackpotEntryStatus::Open)->stake->toBe(9_900)->wager_reference->toBeUuid()
        ->and($entry->picks)->toHaveCount(3)
        ->and($this->api->callsTo('place')[0]['body'])->toMatchArray(['product' => 'sportsbook_jackpot', 'funding' => 'cash', 'stake' => '99.00'])
        ->and($this->api->balanceOf($player))->toBe(90_100);
});

test('an entry is refused without money, picks for every match, or once entries close', function () {
    $jackpot = openJackpot();

    expect(fn () => enterJackpot($this->api->player(5_000), $jackpot, ['1', 'X', '2']))->toThrow(JackpotEntryRefused::class, 'too low');
    expect(fn () => enterJackpot($this->api->player(), $jackpot, ['1', 'X']))->toThrow(JackpotEntryRefused::class, 'every match');

    $this->travelTo($jackpot->closesAt()->addMinute());

    expect(fn () => enterJackpot($this->api->player(), $jackpot, ['1', 'X', '2']))->toThrow(JackpotEntryRefused::class, 'closed');
    expect(JackpotEntry::query()->where('status', JackpotEntryStatus::Open)->count())->toBe(0);
});

test('the jackpot settles once every match has a result, paying each tier its pool at the API', function () {
    $jackpot = openJackpot();
    $allRight = $this->api->player(100_000);
    $twoRight = $this->api->player(100_000);
    $oneRight = $this->api->player(100_000);
    $winner = enterJackpot($allRight, $jackpot, ['1', 'X', '2']);
    $runnerUp = enterJackpot($twoRight, $jackpot, ['1', 'X', '1']);
    $loser = enterJackpot($oneRight, $jackpot, ['1', '1', '1']);

    jackpotResults($jackpot, [[1, 0], [2, 2]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Open)
        ->and(WagerOp::count())->toBe(0);

    jackpotResults($jackpot, [2 => [0, 1]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Settled)
        ->and($winner->fresh())->status->toBe(JackpotEntryStatus::Won)->correct_count->toBe(3)->prize->toBe(1_000_000)
        ->and($runnerUp->fresh())->status->toBe(JackpotEntryStatus::Won)->prize->toBe(100_000)
        ->and($loser->fresh())->status->toBe(JackpotEntryStatus::Lost)->prize->toBe(0)
        ->and($this->api->balanceOf($allRight))->toBe(1_090_100)
        ->and($this->api->balanceOf($twoRight))->toBe(190_100)
        ->and($this->api->balanceOf($oneRight))->toBe(90_100)
        ->and($this->api->wager('sportsbook_jackpot', $loser->wager_reference)['status'])->toBe('lost');
});

test('the API withholds tax on a jackpot prize, and the entry shows it', function () {
    $this->api->taxRates['sportsbook_jackpot'] = 0.2;
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [[1, 0], [2, 2], [0, 1]]);

    // 20% of the 9,901 won above the 99 entry price.
    expect($entry->fresh())->prize->toBe(1_000_000)->tax->toBe(198_020)
        ->and($this->api->balanceOf($player))->toBe(892_080);
});

test('more void matches than the limit cancels the jackpot and the API refunds every entry', function () {
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [null, null, [0, 1]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Cancelled)
        ->and($entry->fresh())->status->toBe(JackpotEntryStatus::Refunded)->prize->toBe(9_900)
        ->and($this->api->callsTo('settle')[0]['body'])->toMatchArray(['product' => 'sportsbook_jackpot', 'outcome' => 'void', 'version' => 1])
        ->and($this->api->balanceOf($player))->toBe(100_000);
});

test('a corrected result takes the prizes back and settles the jackpot again as version 2', function () {
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);
    jackpotResults($jackpot, [[1, 0], [2, 2], [0, 1]]);

    jackpotResults($jackpot, [2 => [1, 1]]);

    expect($jackpot->fresh())->status->toBe(JackpotStatus::Settled)->settlement_version->toBe(2)
        ->and($entry->fresh())->correct_count->toBe(2)->prize->toBe(100_000)
        ->and($this->api->wager('sportsbook_jackpot', $entry->wager_reference))->toMatchArray(['status' => 'won', 'version' => 2])
        ->and($this->api->balanceOf($player))->toBe(190_100);
});

test('staff can cancel an open jackpot; every entry gets its price back', function () {
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    enterJackpot($player, $jackpot, ['1', 'X', '2']);

    app(CancelJackpot::class)->handle($jackpot, 'Feed problems', User::factory()->create());

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Cancelled)
        ->and($this->api->balanceOf($player))->toBe(100_000);
});

test('an entry still being checked holds the settlement and a cancel, until it is found', function () {
    Queue::fake([ReconcileWager::class]);
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    $this->api->next('place', 'lost-answer');
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);

    expect($entry->status)->toBe(JackpotEntryStatus::Pending);
    expect(fn () => app(CancelJackpot::class)->handle($jackpot, 'Feed problems'))->toThrow(ValidationException::class);

    jackpotResults($jackpot, [[1, 0], [2, 2], [0, 1]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Open);

    (new ReconcileWager('jackpot_entry', $entry->id))->handle(app(WagerPlacement::class));

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Settled)
        ->and($entry->fresh()->status)->toBe(JackpotEntryStatus::Won)
        ->and($this->api->balanceOf($player))->toBe(1_090_100);
});

test('a jackpot waiting on an entry that turns out never placed settles once it is rejected', function () {
    Queue::fake([ReconcileWager::class]);
    $jackpot = openJackpot();
    $player = $this->api->player(100_000);
    $winner = enterJackpot($player, $jackpot, ['1', 'X', '2']);
    $this->api->next('place', 'timeout');
    $lost = enterJackpot($this->api->player(100_000), $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [[1, 0], [2, 2], [0, 1]]);
    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Open);

    (new ReconcileWager('jackpot_entry', $lost->id))->handle(app(WagerPlacement::class));

    expect($lost->fresh()->status)->toBe(JackpotEntryStatus::Rejected)
        ->and($jackpot->fresh()->status)->toBe(JackpotStatus::Settled)
        ->and($winner->fresh()->prize)->toBe(1_000_000)
        ->and($this->api->balanceOf($player))->toBe(1_090_100);
});

test('settling a jackpot sends its ops with one sender job, not one per entry', function () {
    $jackpot = openJackpot();
    foreach (range(1, 3) as $i) {
        enterJackpot($this->api->player(100_000), $jackpot, ['1', 'X', '2']);
    }
    Queue::fake([SendWagerOps::class]);

    jackpotResults($jackpot, [[1, 0], [2, 2], [0, 1]]);

    Queue::assertPushed(SendWagerOps::class, 1);
    expect(WagerOp::count())->toBe(3);
});
