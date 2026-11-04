<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Models\User;
use App\Sportsbook\Betting\Jackpot\RecordJackpotResult;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use Illuminate\Support\Facades\DB;

/**
 * Records a fixture's result, from the scores feed or an admin, and settles every bet leg and jackpot match on it.
 *
 * Recording a result again (a correction, adding the half-time score, or voiding the match) first reopens the bets
 * that the earlier result settled, taking back what they paid, and then settles them on the new result.
 */
class RecordResult
{
    public function __construct(private SportsWallet $wallet, private RecordJackpotResult $recordJackpotResult) {}

    /**
     * @param  array{home_score?: int|null, away_score?: int|null, half_time_home_score?: int|null, half_time_away_score?: int|null, is_void?: bool, note?: string|null}  $result
     * @return array{legs: int, bets: int} How many legs were decided and bets settled.
     */
    public function handle(Fixture $fixture, array $result, string $source, ?User $admin = null): array
    {
        return DB::transaction(function () use ($fixture, $result, $source, $admin): array {
            $previous = $fixture->result()->first();

            if ($previous !== null) {
                $this->reopen($fixture);
            }

            $recorded = FixtureResult::updateOrCreate(['fixture_id' => $fixture->id], [
                'home_score' => $result['home_score'] ?? null,
                'away_score' => $result['away_score'] ?? null,
                'half_time_home_score' => $result['half_time_home_score'] ?? null,
                'half_time_away_score' => $result['half_time_away_score'] ?? null,
                'is_void' => $result['is_void'] ?? false,
                'source' => $source,
                'recorded_by_admin_id' => $admin?->id,
                'note' => $result['note'] ?? null,
            ]);

            $fixture->update(['status' => $recorded->is_void ? FixtureStatus::Cancelled : FixtureStatus::Scheduled]);

            $counts = $this->settle($fixture, $recorded);
            $this->recordJackpotResult->handle($fixture, $recorded);

            activity('settlement')
                ->causedBy($admin)
                ->performedOn($fixture)
                ->event($previous === null ? 'result-recorded' : 'result-corrected')
                ->withProperties(array_filter([
                    'fixture' => $fixture->name(),
                    'result' => $recorded->scoreLine(),
                    'previous_result' => $previous?->scoreLine(),
                    'source' => $source,
                    'note' => $recorded->note,
                ], fn (mixed $value): bool => $value !== null))
                ->log($previous === null ? 'Result recorded' : 'Result corrected');

            return $counts;
        });
    }

    /**
     * Settle the open legs on the fixture, then the bets they belong to.
     *
     * @return array{legs: int, bets: int}
     */
    private function settle(Fixture $fixture, FixtureResult $result): array
    {
        $decided = 0;
        $betIds = [];

        foreach ($fixture->betLegs()->where('status', BetLegStatus::Open)->lockForUpdate()->get() as $leg) {
            $status = MarketRules::settle($leg, $result);

            if ($status === null) {
                continue;
            }

            $leg->update(['status' => $status, 'settled_at' => now('UTC')]);
            $decided++;
            $betIds[$leg->bet_id] = true;
        }

        $settled = 0;

        foreach (Bet::query()->whereKey(array_keys($betIds))->get() as $bet) {
            $settled += $this->wallet->settleBet($bet)->status->isSettled() ? 1 : 0;
        }

        return ['legs' => $decided, 'bets' => $settled];
    }

    /**
     * Put the legs the earlier result decided back to open, reopening the settled bets they belong to. Legs voided
     * on their own (postponed, pricing error) stay void.
     */
    private function reopen(Fixture $fixture): void
    {
        $legs = $fixture->betLegs()->where('status', '!=', BetLegStatus::Open)->whereNull('void_reason')->get();

        foreach ($legs->unique('bet_id') as $leg) {
            $this->wallet->reopenBet($leg->bet);
        }

        BetLeg::query()->whereKey($legs->modelKeys())->update(['status' => BetLegStatus::Open, 'settled_at' => null]);
    }
}
